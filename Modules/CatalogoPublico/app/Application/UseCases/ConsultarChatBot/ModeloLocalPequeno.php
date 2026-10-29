<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

final class ModeloLocalPequeno
{
    public static function habilitado(): bool
    {
        return (bool)config('chatbot.use_local_model') && config('app.env') === 'production'
            && rtrim((string)config('app.url'), '/') === config('chatbot.local_model_production_url')
            && rtrim((string)config('chatbot.ollama_url'), '/') === 'http://127.0.0.1:11434';
    }
    /**
     * Qwen sólo selecciona una orientación aprobada. Nunca entrega hechos,
     * números, enlaces ni una decisión sobre la documentación al visitante.
     *
     * @param array<string,string> $opciones
     * @param list<string> $historial
     */
    public function seleccionar(string $pregunta, array $opciones, array $historial = []): ?string
    {
        if (!self::habilitado()
            || trim($pregunta) === '' || mb_strlen($pregunta) > 500) return null;

        $opciones = array_slice(array_filter($opciones, static fn ($v, $k): bool => is_string($v)
            && is_string($k) && (bool) preg_match('/\A[a-z_]{1,24}\z/', $k), ARRAY_FILTER_USE_BOTH), 0, 12, true);
        if ($opciones === [] || array_key_exists('ninguna', $opciones)) return null;
        $historial = array_map(static fn (string $texto): string => mb_substr($texto, 0, 160),
            array_slice(array_values(array_filter($historial, 'is_string')), -2));
        $lock = null;
        try {
            $clave = 'chat:qwen:opcion:v4:'.hash('sha256', json_encode([$pregunta, $opciones, $historial], JSON_THROW_ON_ERROR));
            $cache = Cache::get($clave);
            if (is_string($cache) && isset($opciones[$cache])) return $cache;
            $presupuesto = min(25, max(3, (float) config('chatbot.local_model_timeout_seconds', 25)));
            $lock = Cache::lock('chat:qwen:inferencia', (int) ceil($presupuesto) + 5);
            if (! $lock->get()) return null;

            $modelo = (string) config('chatbot.model');
            $instalado = Cache::remember('chat:qwen:inventario:v2:'.$modelo, 30, function () use ($modelo): bool {
                $inventario = Http::connectTimeout(0.5)->timeout(1)->get('http://127.0.0.1:11434/api/tags');
                if (! $inventario->successful()) return false;
                foreach ((array) $inventario->json('models', []) as $item) {
                    if (is_array($item) && ($item['name'] ?? null) === $modelo && is_int($item['size'] ?? null)
                        && $item['size'] > 0 && $item['size'] <= (int) config('chatbot.max_model_bytes')) return true;
                }
                return false;
            });
            if (! $instalado) return null;
            $respuesta = Http::connectTimeout(0.5)->timeout($presupuesto)
                ->post('http://127.0.0.1:11434/api/chat', [
                    'model' => $modelo, 'stream' => false, 'think' => false, 'keep_alive' => -1,
                    'format' => ['type' => 'string', 'enum' => [...array_keys($opciones), 'ninguna']],
                    'options' => ['temperature' => 0, 'num_predict' => 12, 'num_ctx' => 1024, 'num_thread' => 1],
                    'messages' => [
                        ['role' => 'system', 'content' => 'Clasifica: '.implode('; ', array_map(
                            static fn (string $clave, string $descripcion): string => $clave.'='.mb_substr($descripcion, 0, 60),
                            array_keys($opciones), array_values($opciones),
                        )).'. Interpreta errores de ortografía con contexto. Prioriza la última pregunta y respeta negaciones y cambios de tema. No inventes nombres científicos, lugares, códigos ni cantidades. Ante duda, información insuficiente o tema ajeno: ninguna. Devuelve sólo la clave JSON.'],
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

    public function conversar(string $pregunta, array $historial = [], string $contexto = ''): ?string
    {
        if (! self::habilitado()) return null;
        $lock = null;
        try {
            $lock = Cache::lock('chat:qwen:inferencia', 35);
            if (! $lock->get()) return null;
            return $this->resumir($pregunta, mb_substr($contexto, 0, 2000)."\nPreguntas anteriores: ".implode(' ', array_map(
                static fn (string $t): string => mb_substr($t, 0, 300), array_slice(array_filter($historial, 'is_string'), -2))), true);
        } catch (\Throwable) { return null; }
        finally { try { $lock?->release(); } catch (\Throwable) {} }
    }

    public function resumir(string $pregunta, string $fuente, bool $general = false): ?string
    {
        if (!self::habilitado()) {
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
                'options' => ['temperature' => 0.2, 'num_predict' => $general ? 320 : 180, 'num_thread' => 1, 'num_ctx' => 2048],
                'messages' => [
                    ['role' => 'system', 'content' => $general
                        ? 'Responde en español, breve y claro, a preguntas generales y científicas de cualquier tema. Distingue conocimiento general de datos de HubDigital: para registros, cantidades, estados y procedimientos concretos usa solo el contexto aportado; si no está, no lo inventes. No puedes ejecutar acciones, aprobar documentos ni omitir firmas y permisos. Nunca pidas claves ni certificados. No inventes enlaces ni citas. Ante incertidumbre, indícala.'
                        : 'Responde en español, breve y claro. Usa solo el texto de la fuente. No inventes datos, enlaces ni hechos del museo. Si la fuente no responde, indícalo.'],
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
