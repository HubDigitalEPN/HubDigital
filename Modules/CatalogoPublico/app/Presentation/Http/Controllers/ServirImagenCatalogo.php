<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Presentation\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Modules\CatalogoPublico\Infrastructure\Adapters\StorageImagenesAdapter;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ServirImagenCatalogo
{
    public function __invoke(string $objeto, AlmacenamientoDepositos $almacenamiento): StreamedResponse
    {
        try {
            $ruta = StorageImagenesAdapter::rutaDesdeObjetoPublico($objeto);
            $imagen = $this->imagenPublicadaEnR2($ruta);
            if ($imagen === null) {
                abort(404);
            }

            $sha256 = strtolower(trim((string) $imagen->sha256));
            $mime = $almacenamiento->mimeType($ruta);
            if (! str_starts_with($mime, 'image/')) {
                abort(404);
            }
            $contenido = $almacenamiento->obtenerVerificado($ruta, $sha256 === '' ? null : $sha256);
        } catch (\Throwable) {
            abort(404);
        }

        return response()->stream(function () use ($contenido): void {
            echo $contenido;
        }, 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * El identificador base64 solo localiza el objeto: la publicación se decide
     * aquí contra la relación vigente de divulgación. Así un objeto R2 no queda
     * expuesto al adivinar o conservar una URL de una imagen despublicada.
     */
    private function imagenPublicadaEnR2(string $ruta): ?object
    {
        return DB::table('divulgacion.imagenes_taxonomicas as imagen')
            ->join('taxonomia.especimenes as especimen', 'especimen.occurrence_id', '=', 'imagen.occurrence_id')
            ->join('divulgacion.especimenes_divulgables as divulgable', 'divulgable.especimen_id', '=', 'especimen.id')
            ->where('divulgable.publicado', true)->where('especimen.coordenadas_otras_regiones', false)
            ->where('imagen.ruta', $ruta)
            ->where('imagen.disco', 'r2')
            ->first(['imagen.sha256']);
    }
}
