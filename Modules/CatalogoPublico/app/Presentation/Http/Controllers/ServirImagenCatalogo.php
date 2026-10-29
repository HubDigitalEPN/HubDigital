<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Presentation\Http\Controllers;

use Modules\CatalogoPublico\Infrastructure\ElegibilidadGeograficaPortal;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Modules\CatalogoPublico\Infrastructure\Adapters\StorageImagenesAdapter;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;
use Symfony\Component\HttpFoundation\Response;

final class ServirImagenCatalogo
{
    public function __invoke(string $objeto, AlmacenamientoDepositos $almacenamiento, Request $request): Response
    {
        try {
            $ruta = StorageImagenesAdapter::rutaDesdeObjetoPublico($objeto);
            $imagen = $this->imagenPublicadaEnR2($ruta);
            if ($imagen === null) {
                abort(404);
            }

            $sha256 = strtolower(trim((string) $imagen->sha256));
            // La publicación se comprueba incluso al reutilizar la caché HTTP.
            // Un 304 no necesita HEAD ni descargar de nuevo el objeto de R2.
            $identidad = preg_match('/\A[a-f0-9]{64}\z/', $sha256) ? $sha256 : null;
            if ($identidad !== null) {
                $condicional = response('', 200, ['Cache-Control' => 'public, max-age=300'])->setEtag($identidad);
                if ($condicional->isNotModified($request)) return $condicional;
            }
            $mime = $almacenamiento->mimeType($ruta);
            if (! str_starts_with($mime, 'image/')) {
                abort(404);
            }
            $contenido = $almacenamiento->obtenerVerificado($ruta, $sha256 === '' ? null : $sha256);
        } catch (\Throwable) {
            abort(404);
        }

        $respuesta = response()->stream(function () use ($contenido): void {
            echo $contenido;
        }, 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
        if ($identidad !== null) $respuesta->setEtag($identidad);
        return $respuesta;
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
            ->where('divulgable.publicado', true)->whereRaw(ElegibilidadGeograficaPortal::sql('especimen', 'divulgable'))
            // La imagen heredada se vinculó por etiqueta. Una etiqueta repetida
            // no identifica al dueño; se conserva el objeto para revisión curatorial.
            ->whereRaw('(SELECT COUNT(*) FROM taxonomia.especimenes identidad WHERE identidad.occurrence_id = imagen.occurrence_id) = 1')
            ->where('imagen.ruta', $ruta)
            ->where('imagen.disco', 'r2')
            ->first(['imagen.sha256']);
    }
}
