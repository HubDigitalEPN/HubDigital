<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Validator;

/** Orden editorial. No contiene HTML, selectores ni valores del formulario. */
final class DisposicionContenido
{
    public static function clave(string $ambito): string
    {
        $permitidos = array_merge(array_map(fn ($p) => 'portal:'.$p, array_keys(EditorPortal::paginas())), array_map(fn ($p) => 'wizard:'.$p, array_keys(VistaPreviaWizard::pasos())));
        $ficha = str_starts_with($ambito, 'ficha:') && \Illuminate\Support\Str::isUuid(substr($ambito, 6));
        abort_unless(in_array($ambito, $permitidos, true) || $ficha, 422);
        return 'disposicion.'.str_replace(':', '.', $ambito);
    }

    public static function leer(string $ambito): array
    {
        $dato = json_decode(PortalCopy::all()[self::clave($ambito)] ?? '{}', true);
        return is_array($dato) ? $dato : [];
    }

    public static function guardar(string $ambito, array $grupos, string $version): string
    {
        $posiciones = $grupos['posiciones'] ?? [];
        unset($grupos['posiciones']);
        Validator::make(['grupos' => $grupos, 'posiciones' => $posiciones], [
            'grupos' => ['array', 'max:100'],
            'grupos.*' => ['array', 'max:100'],
            'grupos.*.*' => ['string', 'regex:/^i-[a-f0-9]{16}$/'],
            'posiciones' => ['array', 'max:100'],
        ])->validate();
        foreach ($grupos as $grupo => $items) {
            abort_unless(preg_match('/^g-[a-f0-9]{16}$/D', (string) $grupo), 422);
            abort_unless(count($items) === count(array_unique($items)), 422);
        }
        foreach ($posiciones as $objeto => $horizontal) {
            abort_unless(preg_match('/^[a-z0-9-]{1,80}$/D', (string) $objeto), 422);
            if (is_array($horizontal)) {
                abort_unless(preg_match('/^i-[a-f0-9]{16}$/D', (string) $objeto), 422);
                Validator::make(['posicion' => $horizontal], [
                    'posicion' => ['required', 'array:x,y'],
                    'posicion.x' => ['required', 'integer', 'between:-1000,1000'],
                    'posicion.y' => ['required', 'integer', 'between:-1000,1000'],
                ])->validate();
                $grupos['posiciones'][$objeto] = ['x' => (int) $horizontal['x'], 'y' => (int) $horizontal['y']];
                continue;
            }
            Validator::make(['posicion' => $horizontal], ['posicion' => ['required', 'integer', 'between:0,100']])->validate();
            $grupos['posiciones'][$objeto] = (int) $horizontal;
        }
        $clave = self::clave($ambito);
        EditorPortal::guardar($clave, json_encode($grupos, JSON_THROW_ON_ERROR), $version);
        return EditorPortal::version($clave);
    }

    public static function publicadas(): array
    {
        $salida = [];
        foreach (PortalCopy::all() as $clave => $contenido) {
            if (!str_starts_with($clave, 'disposicion.')) continue;
            $dato = json_decode($contenido, true);
            if (is_array($dato)) $salida[str_replace('disposicion.', '', $clave)] = $dato;
        }
        return $salida;
    }
}
