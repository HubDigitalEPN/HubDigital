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
        abort_unless(in_array($ambito, $permitidos, true), 422);
        return 'disposicion.'.str_replace(':', '.', $ambito);
    }

    public static function leer(string $ambito): array
    {
        $dato = json_decode(PortalCopy::all()[self::clave($ambito)] ?? '{}', true);
        return is_array($dato) ? $dato : [];
    }

    public static function guardar(string $ambito, array $grupos, string $version): string
    {
        Validator::make(['grupos' => $grupos], ['grupos' => ['array', 'max:100'], 'grupos.*' => ['array', 'max:100'], 'grupos.*.*' => ['string', 'regex:/^i-[a-f0-9]{16}$/']])->validate();
        foreach ($grupos as $grupo => $items) {
            abort_unless(preg_match('/^g-[a-f0-9]{16}$/D', (string) $grupo), 422);
            abort_unless(count($items) === count(array_unique($items)), 422);
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
