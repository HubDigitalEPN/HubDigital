<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos;

use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\GeneradorPlantillaImportXlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Descarga la plantilla .xlsx (formateada) para cargar especímenes en grupo.
 * Acceso controlado por el middleware role:curador (definido en routes/web.php).
 */
final class DescargarPlantillaImportController
{
    public function __invoke(): StreamedResponse
    {
        $xlsx = GeneradorPlantillaImportXlsx::generar();
        $nombre = 'plantilla-importacion-especimenes.xlsx';

        return response()->streamDownload(
            function () use ($xlsx): void {
                echo $xlsx;
            },
            $nombre,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Length' => (string) strlen($xlsx),
            ]
        );
    }
}
