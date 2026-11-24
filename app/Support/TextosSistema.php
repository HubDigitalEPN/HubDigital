<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Finder\Finder;

final class TextosSistema
{
    private ?array $textos = null;
    private ?array $plantillas = null;

    public static function normalizar(string $texto): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode($texto, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    /** Inventario editorial de vistas, etiquetas y mensajes; nunca evalúa código. */
    public static function catalogo(): array
    {
        return Cache::remember('textos-sistema-catalogo-v7', 300, static function (): array {
            $catalogo = [];
            $agregar = static function (string $texto, string $pantalla, string $campo = 'Texto o mensaje visible') use (&$catalogo): void {
                $texto = self::normalizar($texto);
                if ($texto === '' || mb_strlen($texto) > 5000 || !preg_match('/\p{L}/u', $texto)
                    || !preg_match('/^[\p{L}\p{N}¿¡]/u', $texto)
                    || preg_match('/[{}<>\[\];@]|(?:\$|=>|::|&&|\|\||https?:\/\/|\\\\)/u', $texto)
                    || preg_match('/^[A-Za-z_][A-Za-z0-9_.]*\s*\(|^[A-Z]{1,3}\d+$|^[A-Za-z_$][A-Za-z0-9_$]*(?:Error|Exception)$|^[a-z]+(?:[A-Z][A-Za-z0-9_]*)+$/D', $texto)
                    || preg_match('/\b(?:SELECT|FROM|WHERE|JOIN|CASE WHEN)\b|\b(?:config|route|asset|session|view)\s*\(/u', $texto)) return;
                $id = hash('sha256', $texto);
                $catalogo[$id] ??= ['original' => $texto, 'pantallas' => [], 'ubicaciones' => []];
                $catalogo[$id]['pantallas'][$pantalla] = $pantalla;
                $catalogo[$id]['ubicaciones'][$pantalla.'|'.$campo] = ['pantalla'=>$pantalla,'campo'=>$campo];
            };
            $directorios = [resource_path('views'), resource_path('js'), public_path('js'), app_path(), base_path('Modules')];
            // El inventario es del código editorial propio. No recorrer miles
            // de dependencias ni sus copias compiladas para abrir un texto.
            $archivos = self::archivos($directorios);
            foreach ($archivos as $archivo) {
                $ruta = str_replace('\\', '/', $archivo->getPathname());
                if ((!str_ends_with($ruta, '.php') && !str_ends_with($ruta, '.js')) || str_contains($ruta, '/tests/') || str_contains($ruta, '/database/') || str_contains($ruta, '/Console/')) continue;
                if (str_contains($ruta, '/Modules/') && !str_contains($ruta, '/resources/') && !str_contains($ruta, '/app/')) continue;
                $pantalla = self::pantalla($ruta);
                $fuente = $archivo->getContents();
                // Literales de Alpine y JavaScript: se muestran sólo textos con
                // forma de etiqueta o mensaje, no clases CSS, rutas ni expresiones.
                preg_match_all('/\x27((?:[^\x27\\\\]|\\\\.)*)\x27|"((?:[^"\\\\]|\\\\.)*)"/su', $fuente, $literales, PREG_SET_ORDER);
                foreach ($literales as $literal) {
                    $texto = $literal[1] !== '' ? $literal[1] : ($literal[2] ?? '');
                    if (preg_match('/^[\p{Lu}¿¡]|[áéíóúñ]/u', $texto)) $agregar(str_replace(["\\'", '\\"'], ["'", '"'], $texto), $pantalla);
                }
                if (str_ends_with($ruta, '.blade.php')) {
                    $html = preg_replace('/{{--.*?--}}|<script\b.*?<\/script>|<style\b.*?<\/style>/su', '', $fuente);
                    $html = preg_replace('/{{.*?}}|{!!.*?!!}/su', '', $html);
                    $html = preg_replace('/@\w+[ \t]*(\((?:[^()]++|(?1))*\))?/u', '', $html);
                    preg_match_all('/>([^<>]+)</u', $html, $textos);
                    foreach ($textos[1] as $texto) {
                        $texto = preg_replace('/{{.*?}}|{!!.*?!!}|@(?:if|elseif|foreach|php|error|class)\([^\n]*?\)|@\w+/su', '', $texto);
                        $agregar($texto, $pantalla);
                    }
                    preg_match_all('/(label|title|placeholder|aria-label|wire:confirm(?:\.prompt)?)="([^"{}$]+)"/u', $html, $atributos, PREG_SET_ORDER);
                    foreach ($atributos as [, $atributo, $texto]) {
                        if (str_starts_with($atributo, 'wire:confirm')) {
                            $agregar(explode('|', $texto, 2)[0], $pantalla, 'Confirmación');
                            continue;
                        }
                        $agregar($texto, $pantalla, match ($atributo) {
                            'label' => 'Etiqueta del campo «'.$texto.'»',
                            'placeholder' => 'Indicación dentro del campo «'.$texto.'»',
                            'title' => 'Ayuda emergente «'.$texto.'»',
                            default => 'Nombre accesible del control «'.$texto.'»',
                        });
                    }
                }
                foreach (token_get_all($fuente) as $token) {
                    if (!is_array($token) || $token[0] !== T_CONSTANT_ENCAPSED_STRING) continue;
                    $texto = substr($token[1], 1, -1);
                    if (preg_match('/\s|[áéíóúñÁÉÍÓÚÑ]/u', $texto)) $agregar(str_replace(["\\'", '\\"'], ["'", '"'], $texto), $pantalla);
                }
            }
            foreach (['portal-copy', 'wizard-copy'] as $nombre) {
                $recorrer = static function (array $datos) use (&$recorrer, $agregar, $nombre): void {
                    foreach ($datos as $valor) { if (is_array($valor)) $recorrer($valor); elseif (is_string($valor)) $agregar($valor, $nombre); }
                };
                $recorrer(config($nombre, []));
            }
            uasort($catalogo, static fn ($a, $b) => strnatcasecmp($a['original'], $b['original']));
            return $catalogo;
        });
    }

    private static function archivos(array $directorios): Finder
    {
        return Finder::create()->files()->in($directorios)
            ->exclude(['node_modules', 'vendor', 'build', 'build-catalogopublico', 'build-gestionprestamosrecepciones', 'build-inventariogestioncoleccion'])
            ->name(['*.php', '*.js']);
    }

    private static function pantalla(string $ruta): string
    {
        if (str_contains($ruta, '/CatalogoPublico/') || preg_match('/\/(?:portal-[^\/]+|mapa-pantalla-completa)\.(?:js|blade\.php)$/', $ruta)) {
            return 'Catálogo público y mapa';
        }
        $nombre = preg_replace('/\.(?:blade\.php|php|js)$/', '', basename($ruta));
        if ($nombre === 'index') $nombre = basename(dirname($ruta));
        $nombre = preg_replace('/(?<=\p{Ll})(\p{Lu})/u', ' $1', str_replace(['-', '_'], ' ', $nombre));
        $nombre = mb_convert_case($nombre, MB_CASE_TITLE, 'UTF-8');
        $nombre = str_replace(['Configuracion', 'Administracion', 'Especimenes', 'Taxonomia'], ['Configuración', 'Administración', 'Especímenes', 'Taxonomía'], $nombre);
        $grupo = str_contains($ruta, '/InventarioGestionColeccion/') ? 'Catálogo' : (str_contains($ruta, '/GestionPrestamosRecepciones/') ? 'Préstamos y depósitos' : 'Sistema');
        if (str_contains(mb_strtolower($ruta), '/administracion/')) $grupo = 'Administración';
        return $grupo.' · '.$nombre;
    }

    public static function diccionario(): array
    {
        $servicio = app(self::class);
        if ($servicio->textos !== null) return $servicio->textos;
        try {
            return $servicio->textos = Cache::remember('textos-sistema-v1', 300, static fn () => Schema::hasTable('usuarios.textos_sistema')
                ? DB::table('usuarios.textos_sistema')->pluck('contenido', 'original')->all()
                : []);
        } catch (\Throwable $error) {
            \Illuminate\Support\Facades\Log::warning('No se pudieron cargar los textos del sistema.', ['tipo' => $error::class]);
            return $servicio->textos = [];
        }
    }

    public static function olvidar(): void
    {
        $servicio = app(self::class);
        $servicio->textos = $servicio->plantillas = null;
        Cache::forget('textos-sistema-v1');
    }

    public static function mensaje(string $original): string
    {
        $diccionario = self::diccionario();
        $normalizado = self::normalizar($original);
        if (isset($diccionario[$normalizado])) {
            return $diccionario[$normalizado];
        }
        if (mb_strlen($normalizado) > 5000) {
            return $original;
        }

        $servicio = app(self::class);
        $servicio->plantillas ??= self::plantillas($diccionario);
        foreach ($servicio->plantillas as [$patron, $marcadores, $contenido]) {
            if (preg_match($patron, $normalizado, $valores)) {
                array_shift($valores);

                return strtr($contenido, array_combine($marcadores, $valores));
            }
        }

        return $original;
    }

    private static function plantillas(array $diccionario): array
    {
        $plantillas = [];
        foreach ($diccionario as $original => $contenido) {
            $partes = preg_split('/(\{[a-z][a-z0-9_]*\})/', $original, -1, PREG_SPLIT_DELIM_CAPTURE);
            $marcadores = [];
            $patron = '';
            $fijo = '';
            foreach ($partes as $i => $parte) {
                if ($i % 2 === 0) {
                    $patron .= preg_quote($parte, '~');
                    $fijo .= $parte;
                } else {
                    $patron .= '(.*?)';
                    $marcadores[] = $parte;
                }
            }
            if ($marcadores !== [] && count($marcadores) <= 6 && mb_strlen(trim($fijo)) >= 10) {
                $plantillas[] = ['~\A'.$patron.'\z~u', $marcadores, $contenido];
            }
        }

        return $plantillas;
    }

    public static function confirmacion(string $texto, bool $solicitaRespuesta): string
    {
        $texto = html_entity_decode($texto, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (!$solicitaRespuesta || !str_contains($texto, '|')) {
            return self::mensaje($texto);
        }

        [$pregunta, $respuesta] = explode('|', $texto, 2);

        return str_replace('|', ' ', self::mensaje($pregunta)).'|'.$respuesta;
    }

    public static function compilarConfirmaciones(string $vista): string
    {
        return preg_replace_callback('/(?<=\s)(wire:confirm(?:\.prompt)?)="([^"{}$\r\n]+)"/u', static function (array $coincidencia): string {
            [$atributo, $valor] = array_slice($coincidencia, 1);
            $respuesta = $atributo === 'wire:confirm.prompt' ? 'true' : 'false';

            return $atributo.'="{{ \\App\\Support\\TextosSistema::confirmacion('.var_export($valor, true).', '.$respuesta.') }}"';
        }, $vista) ?? $vista;
    }
}
