<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Genera la plantilla .xlsx que el curador descarga para cargar especímenes en
 * grupo: hoja "Especímenes" con solo la fila de encabezados formateada (para
 * rellenar debajo) y hoja "Guía" que describe cada columna con un ejemplo.
 *
 * Los encabezados usan los mismos nombres Darwin Core que reconoce el importador
 * (ver FilaCatalogoMapper), de modo que el archivo llenado se puede subir tal cual.
 */
final class GeneradorPlantillaImportXlsx
{
    /**
     * Columnas de la plantilla en orden, con su descripción/ejemplo para la guía.
     *
     * @return array<string, string>
     */
    public static function columnas(?array $definicion = null): array
    {
        $definicion ??= ConfiguracionPlantillaCatalogo::predeterminada();
        return array_column($definicion['columnas'], 'descripcion', 'cabecera');
    }

    /** Cabeceras CSV completas, incluidas las que contienen comas. */
    public static function generarCsv(?array $definicion = null): string
    {
        $cabeceras = array_map(static fn (string $campo): string => '"'.str_replace('"', '""', $campo).'"', array_keys(self::columnas($definicion)));
        return "\xEF\xBB\xBF".implode(',', $cabeceras)."\r\n";
    }

    /** Devuelve el contenido binario del .xlsx de la plantilla. */
    public static function generar(?array $definicion = null): string
    {
        $definicion ??= ConfiguracionPlantillaCatalogo::predeterminada();
        $columnas = self::columnas($definicion);
        $encabezados = array_keys($columnas);

        $spreadsheet = new Spreadsheet;

        // ── Hoja 1: Especímenes (solo encabezados, para rellenar debajo) ──────
        $hoja = $spreadsheet->getActiveSheet();
        $hoja->setTitle('Especímenes');
        $hoja->fromArray([$encabezados], null, 'A1');

        $ultima = Coordinate::stringFromColumnIndex(count($encabezados));
        $hoja->getStyle("A1:{$ultima}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1B365D']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        foreach (range(1, count($encabezados)) as $col) {
            $hoja->getColumnDimensionByColumn($col)->setAutoSize(true);
        }
        $hoja->setAutoFilter("A1:{$ultima}1");
        $hoja->freezePane('A2');

        // ── Hoja 2: Guía (columna → descripción) ──────────────────────────────
        $guia = $spreadsheet->createSheet();
        $guia->setTitle('Guía');
        $guia->fromArray([['Campo', 'Columna', 'Descripción y ejemplo', 'Requisito', 'Formato']], null, 'A1');
        $fila = 2;
        foreach ($definicion['columnas'] as $c) {
            $guia->fromArray([[$c['campo'], $c['cabecera'], $c['descripcion'], $c['requisito'], $c['formato']]], null, 'A'.$fila);
            $fila++;
        }
        $guia->getStyle('A1:E1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1B365D']],
        ]);
        foreach (['A' => 32, 'B' => 32, 'C' => 100, 'D' => 18, 'E' => 16] as $col => $ancho) $guia->getColumnDimension($col)->setWidth($ancho);
        $guia->getStyle('A2:E'.($fila - 1))->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        $guia->freezePane('C2');
        $guia->setAutoFilter('A1:E'.($fila - 1));
        // Todo texto se escribe explícitamente: ni encabezados ni guía son fórmulas.
        foreach ([$hoja, $guia] as $sheet) foreach ($sheet->getRowIterator() as $row) foreach ($row->getCellIterator() as $cell) {
            if ($cell->getValue() !== null) $cell->setValueExplicit((string)$cell->getValue(), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        }

        $spreadsheet->setActiveSheetIndex(0);

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $contenido = (string) ob_get_clean();

        $spreadsheet->disconnectWorksheets();

        return $contenido;
    }
}
