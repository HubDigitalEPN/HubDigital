<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class EditorPortal
{
    public static function paginas(): array
    {
        return ['inicio' => 'home', 'laboratorio' => 'laboratorio', 'depositos' => 'depositos.portal',
            'catalogo' => 'portal.catalogo', 'importacion' => 'inventario.taxonomia.importar', 'privacidad' => 'portal.privacidad', 'terminos' => 'portal.terminos',
            'licencia' => 'portal.licencia', 'citar' => 'portal.citar', 'creditos' => 'portal.creditos'];
    }

    public static function pagina(string $grupo): string
    {
        return match ($grupo) { 'navegacion', 'contacto' => 'inicio', 'indicadores', 'figuras' => 'catalogo', default => $grupo };
    }

    public static function diccionario(): array
    {
        $pagina = array_search(request()->route()?->getName(), self::paginas(), true);
        if ($pagina === false) return [];
        $salida = [];
        foreach (PortalCopy::all() as $clave => $contenido) {
            if (!str_starts_with($clave, 'editorial.'.$pagina.'.')) continue;
            $dato = json_decode($contenido, true);
            if (is_array($dato) && is_string($dato['original'] ?? null) && is_string($dato['texto'] ?? null)) $salida[$dato['original']] = $dato['texto'];
        }
        return $salida;
    }

    public static function version(string $clave): string
    {
        return hash('sha256', (string) DB::table('usuarios.textos_portal')->where('clave', $clave)->value('contenido'));
    }

    public static function guardar(string $clave, string $contenido, string $version): void
    {
        DB::transaction(function () use ($clave, $contenido, $version): void {
            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ['editor-portal:'.$clave]);
            if (!hash_equals(self::version($clave), $version)) throw ValidationException::withMessages(['textoActual' => 'Otra persona cambió este texto. Vuelve a seleccionarlo antes de guardar.']);
            DB::table('usuarios.textos_portal')->updateOrInsert(['clave' => $clave], ['contenido' => $contenido, 'updated_by' => auth()->id(), 'updated_at' => now()]);
        });
        PortalCopy::forget();
    }
}
