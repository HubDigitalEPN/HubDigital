<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/** Consulta breve a una fuente pública; no carga modelos ni ejecuta expresiones del visitante. */
final class FuentesPublicasChat
{
    public function responder(string $pregunta): array
    {
        $normal = mb_strtolower(Str::ascii(trim($pregunta)));
        if (preg_match('/^(?:[¿?\s]*(?:cuanto es|calcula|cuanto da)\s+)?(-?\d{1,12}(?:[.,]\d{1,8})?)\s*(\+|mas|menos|\-|por|[x*×]|entre|dividido por|\/)\s*(-?\d{1,12}(?:[.,]\d{1,8})?)[?\s]*$/u', $normal, $m)) {
            $a = (float) str_replace(',', '.', $m[1]);
            $b = (float) str_replace(',', '.', $m[3]);
            $valor = match ($m[2]) {
                '+', 'mas' => $a + $b, '-', 'menos' => $a - $b,
                'por', 'x', '*', '×' => $a * $b,
                default => $b == 0.0 ? null : $a / $b,
            };
            return ['texto' => $valor === null ? 'No se puede dividir entre cero.' : 'El resultado es '.rtrim(rtrim(number_format($valor, 8, ',', ''), '0'), ',').'.',
                'opciones' => [], 'fuente' => 'calculo', 'intent' => 'general.calculo'];
        }

        if (! config('chatbot.public_sources', true)) {
            return $this->sinFuente();
        }

        $tema = preg_replace('/^[¿?\s]*(?:(?:qu[eé]|cu[aá]l(?:es)?)\s+(?:es|son)\s+|(?:explica(?:me)?|cu[eé]ntame|habla(?:me)?)\s+(?:sobre\s+)?)(?:(?:el|la|los|las|un|una)\s+)?/iu', '', trim($pregunta));
        $tema = mb_substr(trim((string) $tema, " \t\n\r?¿"), 0, 160);
        $clave = 'portal:fuente-publica:v1:'.hash('sha256', mb_strtolower($tema));
        try {
            $guardada = Cache::get($clave);
            if (is_array($guardada)) return $guardada;
        } catch (\Throwable) {
            // La respuesta no depende de la disponibilidad de la caché.
        }

        try {
            $respuesta = Http::withHeaders(['User-Agent' => 'HubDigital/1.0 (portal educativo; https://dev.labinvepn.org)'])
                ->connectTimeout(2)->timeout(5)->withOptions(['allow_redirects' => false])
                ->get('https://es.wikipedia.org/w/api.php', [
                    'action' => 'query', 'generator' => 'search', 'gsrsearch' => $tema,
                    'gsrlimit' => 3, 'prop' => 'extracts|info', 'exintro' => 1,
                    'explaintext' => 1, 'exchars' => 1100, 'exlimit' => 3, 'inprop' => 'url',
                    'format' => 'json', 'formatversion' => 2,
                ]);
            if (! $respuesta->successful()) return $this->sinFuente();
            $paginas = $respuesta->json('query.pages', []);
            if (! is_array($paginas)) return $this->sinFuente();
            usort($paginas, static fn ($a, $b) => ($a['index'] ?? 999) <=> ($b['index'] ?? 999));
            foreach ($paginas as $pagina) {
                $extracto = trim(strip_tags((string) ($pagina['extract'] ?? '')));
                $url = (string) ($pagina['fullurl'] ?? '');
                if ($extracto === '' || ! str_starts_with($url, 'https://es.wikipedia.org/wiki/')) continue;
                $resultado = ['texto' => 'Encontré información sobre «'.($pagina['title'] ?? $tema).'» en Wikipedia: '.Str::limit($extracto, 1000).
                    '\n\nEsta es una referencia enciclopédica externa; no son datos de la colección ni una verificación de actualidad.',
                    'opciones' => [['label' => 'Leer fuente: Wikipedia', 'url' => $url], ['label' => 'Volver al menú del portal', 'pregunta' => 'menú']],
                    'fuente' => 'fuente_publica', 'intent' => 'general.enciclopedia'];
                $resultado['texto'] = str_replace('\\n', "\n", $resultado['texto']);
                try { Cache::put($clave, $resultado, 3600); } catch (\Throwable) {}
                return $resultado;
            }
        } catch (\Throwable) {
            // Tiempo acotado: una fuente caída no bloquea el chat ni la VM.
        }

        return $this->sinFuente();
    }

    private function sinFuente(): array
    {
        return ['texto' => 'No encontré una fuente pública disponible que permita responder con suficiente información. Prueba con el nombre del tema o reformula la pregunta. Puedo consultar temas generales, pero no verificar noticias en tiempo real ni elaborar respuestas abiertas como un modelo de IA.',
            'opciones' => [['label' => 'Menú del portal', 'pregunta' => 'menú']], 'fuente' => 'unknown', 'intent' => 'general.sin_fuente'];
    }
}
