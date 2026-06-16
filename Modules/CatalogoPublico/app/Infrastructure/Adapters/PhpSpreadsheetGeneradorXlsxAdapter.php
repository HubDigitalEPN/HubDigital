<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Infrastructure\Adapters;

use Modules\CatalogoPublico\Application\Ports\GeneradorXlsxPort;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class PhpSpreadsheetGeneradorXlsxAdapter implements GeneradorXlsxPort
{
    public function generar(array $encabezados, array $filas): string
    {
        $spreadsheet = new Spreadsheet;
        $hoja = $spreadsheet->getActiveSheet();
        $hoja->setTitle('Especímenes');

        $hoja->fromArray([$encabezados], null, 'A1');

        $ultimaColumna = Coordinate::stringFromColumnIndex(count($encabezados));

        $hoja->getStyle("A1:{$ultimaColumna}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1B365D']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        if ($filas !== []) {
            $hoja->fromArray($filas, null, 'A2');
        }

        foreach (range(1, count($encabezados)) as $col) {
            $hoja->getColumnDimensionByColumn($col)->setAutoSize(true);
        }

        $hoja->setAutoFilter("A1:{$ultimaColumna}1");

        $writer = new Xlsx($spreadsheet);

        ob_start();
        $writer->save('php://output');

        return (string) ob_get_clean();
    }
}
