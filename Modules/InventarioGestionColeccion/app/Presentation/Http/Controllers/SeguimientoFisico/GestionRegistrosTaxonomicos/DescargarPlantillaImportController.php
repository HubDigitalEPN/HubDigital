<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos;

use Illuminate\Http\Response;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\GeneradorPlantillaImportXlsx;

/**
 * Descarga la plantilla .xlsx (formateada) para cargar especímenes en grupo.
 * Acceso controlado por el middleware role:curador (definido en routes/web.php).
 *
 * Se devuelve una respuesta binaria directa (no streamed): es más robusta detrás
 * de proxies/túneles y evita descargas corruptas por buffers de salida.
 */
final class DescargarPlantillaImportController
{
    public function __invoke(): Response
    {
        $xlsx = GeneradorPlantillaImportXlsx::generar();

        return response($xlsx, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="plantilla-importacion-especimenes.xlsx"',
            'Content-Length' => (string) strlen($xlsx),
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }
}
