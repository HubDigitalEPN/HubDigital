<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

final class ModeloLocalPequeno
{
    /**
     * Qwen sólo selecciona una orientación aprobada. Nunca entrega hechos,
     * números, enlaces ni una decisión sobre la documentación al visitante.
     *
     * @param array<string,string> $opciones
     * @param list<string> $historial
     */
    public function seleccionar(string $pregunta, array $opciones, array $historial = []): ?string
    {
        if (! config('chatbot.use_local_model') || config('app.env') !== 'production'
            || rtrim((string) config('app.url'), '/') !== config('chatbot.local_model_production_url')
            || rtrim((string) config('chatbot.ollama_url'), '/') !== 'http://127.0.0.1:11434'
            || trim($pregunta) === '' || mb_strlen($pregunta) > 500) return null;

        $opciones = array_slice(array_filter($opciones, static fn ($v, $k): bool => is_string($v)
            && is_string($k) && (bool) preg_match('/\A[a-z_]{1,24}\z/', $k), ARRAY_FILTER_USE_BOTH), 0, 12, true);
        if ($opciones === [] || array_key_exists('ninguna', $opciones)) return null;
        $historial = array_map(static fn (string $texto): string => mb_substr($texto, 0, 160),
            array_slice(array_values(array_filter($historial, 'is_string')), -2));
        $lock = null;
        try {
            $clave = 'chat:qwen:opcion:v2:'.hash('sha256', json_encode([$pregunta, $opciones, $historial], JSON_THROW_ON_ERROR));
            $cache = Cache::get($clave);
            if (is_string($cache) && isset($opciones[$cache])) return $cache;
            $lock = Cache::lock('chat:qwen:inferencia', 10);
            if (! $lock->get()) return null;

            $modelo = (string) config('chatbot.model');
            $instalado = Cache::remember('chat:qwen:inventario:v1', 30, function () use ($modelo): bool {
                $inventario = Http::connectTimeout(0.5)->timeout(1)->get('http://127.0.0.1:11434/api/tags');
                if (! $inventario->successful()) return false;
                foreach ((array) $inventario->json('models', []) as $item) {
                    if (is_array($item) && ($item['name'] ?? null) === $modelo && is_int($item['size'] ?? null)
                        && $item['size'] > 0 && $item['size'] <= (int) config('chatbot.max_model_bytes')) return true;
                }
                return false;
            });
            if (! $instalado) return null;
            $respuesta = Http::connectTimeout(0.5)->timeout((float) config('chatbot.local_model_timeout_seconds', 3))
                ->post('http://127.0.0.1:11434/api/chat', [
                    'model' => $modelo, 'stream' => false, 'think' => false, 'keep_alive' => -1,
                    'format' => ['type' => 'string', 'enum' => [...array_keys($opciones), 'ninguna']],
                    'options' => ['temperature' => 0, 'num_predict' => 12, 'num_ctx' => 1024, 'num_thread' => 1],
                    'messages' => [
                        ['role' => 'system', 'content' => 'Clasifica: '.implode('; ', array_map(
                            static fn (string $clave, string $descripcion): string => $clave.'='.mb_substr($descripcion, 0, 60),
                            array_keys($opciones), array_values($opciones),
                        )).'. Otro tema: ninguna. Sólo el nombre JSON.'],
                        ['role' => 'user', 'content' => ($historial === [] ? '' : 'Antes: '.implode(' ', $historial)."\n").$pregunta],
                    ],
                ]);
            if (! $respuesta->successful()) return null;
            $opcion = json_decode((string) $respuesta->json('message.content', ''), true);
            if (! is_string($opcion) || ! isset($opciones[$opcion])) return null;
            Cache::put($clave, $opcion, 600);
            return $opcion;
        } catch (\Throwable) {
            // Una demora, caída o respuesta no acreditada conserva la ayuda determinística.
            return null;
        } finally {
            try { $lock?->release(); } catch (\Throwable) { /* El fallback tampoco depende del almacén de locks. */ }
        }
    }

    public function resumir(string $pregunta, string $fuente): ?string
    {
        if (! config('chatbot.use_local_model')) {
            return null;
        }

        $url = rtrim((string) config('chatbot.ollama_url'), '/');
        $modelo = (string) config('chatbot.model');
        $maximo = (int) config('chatbot.max_model_bytes');

        try {
            $inventario = Http::timeout(2)->get($url.'/api/tags');
            if (! $inventario->successful()) {
                return null;
            }

            $coincidencias = array_filter(
                (array) $inventario->json('models', []),
                static fn (mixed $item): bool => is_array($item) && ($item['name'] ?? null) === $modelo,
            );
            $instalado = reset($coincidencias);
            $tamano = is_array($instalado) ? ($instalado['size'] ?? null) : null;
            if (! is_int($tamano) || $tamano <= 0 || $tamano > $maximo) {
                return null;
            }

            $respuesta = Http::timeout(30)->post($url.'/api/chat', [
                'model' => $modelo,
                'stream' => false,
                'think' => false,
                'options' => ['temperature' => 0.2, 'num_predict' => 180],
                'messages' => [
                    ['role' => 'system', 'content' => 'Responde en español, breve y claro. Usa solo el texto de la fuente. No inventes datos, enlaces ni hechos del museo. Si la fuente no responde, indícalo.'],
                    ['role' => 'user', 'content' => "Pregunta: {$pregunta}\n\nFuente: {$fuente}"],
                ],
            ]);

            $texto = trim((string) $respuesta->json('message.content', ''));

            return $respuesta->successful() && $texto !== '' ? $texto : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
