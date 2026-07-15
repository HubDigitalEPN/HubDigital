<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Vuelca una tabla (columnas + filas) a un .xlsx formateado: encabezado en
 * azul-marino, autofiltro y panel congelado. Se usa para descargar la selección
 * de especímenes de la pantalla de Publicación GBIF.
 */
final class GeneradorEspecimenesXlsx
{
    /**
     * @param  string[]  $columnas
     * @param  array<int, array<string, string>>  $filas
     */
    public static function generar(array $columnas, array $filas): string
    {
        $spreadsheet = new Spreadsheet;
        $hoja = $spreadsheet->getActiveSheet();
        $hoja->setTitle('Especímenes');

        $hoja->fromArray([$columnas], null, 'A1');

        $matriz = [];
        foreach ($filas as $fila) {
            $matriz[] = array_map(static fn (string $c): string => $fila[$c] ?? '', $columnas);
        }
        if ($matriz !== []) {
            $hoja->fromArray($matriz, null, 'A2', true);
        }

        $ultima = Coordinate::stringFromColumnIndex(max(1, count($columnas)));
        $hoja->getStyle("A1:{$ultima}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1B365D']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        foreach (range(1, count($columnas)) as $col) {
            $hoja->getColumnDimensionByColumn($col)->setAutoSize(true);
        }
        $hoja->setAutoFilter("A1:{$ultima}1");
        $hoja->freezePane('A2');

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $contenido = (string) ob_get_clean();

        $spreadsheet->disconnectWorksheets();

        return $contenido;
    }
}
