<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers;

use App\Support\PlantillasLaboratorio;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Genera la plantilla .xlsx que el curador descarga para cargar especímenes en
 * grupo: hoja "Especímenes" con encabezados e instrucciones (datos desde
 * la fila 3) y hoja "Guía" que describe cada columna con un ejemplo.
 *
 * Los encabezados usan los mismos nombres Darwin Core que reconoce el importador
 * (ver FilaCatalogoMapper), de modo que el archivo llenado se puede subir tal cual.
 */
final class GeneradorPlantillaImportXlsx
{
    private static ?array $requisitosReferencia = null;
    public static function requisitoGuia(array $columna): string
    {
        self::$requisitosReferencia ??= array_column(PlantillasLaboratorio::referencia()['catalogo'], 'requisito_guia', 'campo');
        return $columna['requisito_guia'] ?? self::$requisitosReferencia[$columna['campo']] ?? $columna['requisito'];
    }

    public static function colorRequisito(string $requisito): string
    {
        return match ($requisito) { 'obligatorio' => 'FFC000', 'recomendado' => 'FFFF00', 'interno' => 'BFBFBF', 'predeterminado' => 'FFFFFF', default => 'FFFFFF' };
    }
    /**
     * Columnas de la plantilla en orden, con su descripción/ejemplo para la guía.
     *
     * @return array<string, string>
     */
    public static function columnas(?array $definicion = null): array
    {
        return array_column(self::visibles($definicion), 'descripcion', 'cabecera');
    }

    private static function visibles(?array $definicion): array
    {
        $definicion ??= ConfiguracionPlantillaCatalogo::predeterminada();

        return array_values(array_filter($definicion['columnas'], static fn (array $columna): bool => $columna['plantilla_catalogo'] ?? true));
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
        $columnas = self::visibles($definicion);
        $encabezados = array_column($columnas, 'cabecera');

        $spreadsheet = new Spreadsheet;

        // ── Hoja 1: Especímenes (encabezados, instrucciones y datos desde fila 3) ──
        $hoja = $spreadsheet->getActiveSheet();
        $hoja->setTitle('Especímenes');
        $hoja->fromArray([$encabezados], null, 'A1');
        $instrucciones = array_column($columnas, 'descripcion');
        $instrucciones[0] = '[INSTRUCCIONES] '.$instrucciones[0];
        $hoja->fromArray([$instrucciones], null, 'A2');

        $ultima = Coordinate::stringFromColumnIndex(count($encabezados));
        $hoja->getStyle("A1:{$ultima}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1B365D']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        foreach (range(1, count($encabezados)) as $col) {
            $hoja->getColumnDimensionByColumn($col)->setWidth(28);
            $requisito = self::requisitoGuia($columnas[$col - 1]);
            $celda = Coordinate::stringFromColumnIndex($col).'1';
            $hoja->getStyle($celda)->getFill()->setFillType($requisito === 'predeterminado' ? Fill::FILL_PATTERN_GRAY0625 : Fill::FILL_SOLID)->getStartColor()->setRGB(self::colorRequisito($requisito));
            $hoja->getStyle($celda)->getFont()->getColor()->setRGB('000000');
        }
        $hoja->getStyle("A1:{$ultima}2")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        $hoja->getRowDimension(1)->setRowHeight(40);
        $hoja->getRowDimension(2)->setRowHeight(140);
        $hoja->getStyle("A2:{$ultima}2")->getFont()->setSize(10);
        $hoja->setAutoFilter("A1:{$ultima}1");
        $hoja->freezePane('A3');

        // ── Hoja 2: Guía (columna → descripción) ──────────────────────────────
        $guia = $spreadsheet->createSheet();
        $guia->setTitle('Guía');
        $guia->fromArray([['Campo', 'Columna', 'Descripción y ejemplo', 'Requisito guía v2', 'Formato', 'Requisito catálogo']], null, 'A1');
        $fila = 2;
        foreach ($columnas as $c) {
            $requisito = self::requisitoGuia($c);
            $guia->fromArray([[$c['campo'], $c['cabecera'], $c['descripcion'], $requisito, $c['formato'], $c['requisito']]], null, 'A'.$fila);
            $guia->getStyle('D'.$fila)->getFill()->setFillType($requisito === 'predeterminado' ? Fill::FILL_PATTERN_GRAY0625 : Fill::FILL_SOLID)->getStartColor()->setRGB(self::colorRequisito($requisito));
            $fila++;
        }
        $guia->getStyle('A1:F1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1B365D']],
        ]);
        foreach (['A' => 32, 'B' => 32, 'C' => 100, 'D' => 22, 'E' => 16, 'F' => 22] as $col => $ancho) $guia->getColumnDimension($col)->setWidth($ancho);
        $guia->getComment('F1')->getText()->createTextRun('Reglas de importación del catálogo. Los colores reproducen la guía v2; sus requisitos para depósito son independientes. La fila 2 de Especímenes contiene instrucciones; comienza los datos en la fila 3.');
        $guia->getStyle('A2:F'.($fila - 1))->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        $guia->freezePane('C2');
        $guia->setAutoFilter('A1:F'.($fila - 1));
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
