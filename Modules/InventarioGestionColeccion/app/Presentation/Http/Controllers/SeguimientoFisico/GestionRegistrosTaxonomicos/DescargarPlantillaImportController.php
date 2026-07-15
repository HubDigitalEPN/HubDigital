<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos;

use Illuminate\Http\Response;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\GeneradorPlantillaImportXlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Descarga la plantilla para cargar especímenes en grupo.
 *
 * Devuelve un .xlsx formateado cuando phpoffice/phpspreadsheet está disponible;
 * si no lo está (dependencia sin instalar en el servidor), cae a una plantilla
 * .csv con los mismos encabezados —el importador acepta CSV—, para que la
 * descarga NUNCA falle con un 500.
 *
 * Respuesta binaria directa (no streamed): más robusta detrás de proxies/túneles.
 * Acceso controlado por el middleware role:curador (definido en routes/web.php).
 */
final class DescargarPlantillaImportController
{
    public function __invoke(): Response
    {
        if (class_exists(Spreadsheet::class)) {
            $xlsx = GeneradorPlantillaImportXlsx::generar();

            return response($xlsx, 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="plantilla-importacion-especimenes.xlsx"',
                'Content-Length' => (string) strlen($xlsx),
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ]);
        }

        // Fallback sin dependencias: CSV con los mismos encabezados. El BOM UTF-8
        // hace que Excel lo abra con los acentos correctos.
        $encabezados = array_keys(GeneradorPlantillaImportXlsx::columnas());
        $csv = "\xEF\xBB\xBF".implode(',', $encabezados)."\r\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="plantilla-importacion-especimenes.csv"',
            'Content-Length' => (string) strlen($csv),
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }
}
