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
    public static function columnas(): array
    {
        return [
            'occurrenceID' => 'Identificador único de la ocurrencia (ej. MEPN:INV:12345). Si se omite, se genera automáticamente.',
            'catalogNumber' => 'Número de catálogo (ej. MEPN-INV-000123).',
            'oldCode' => 'Código antiguo o de campo; agrupa especímenes de la misma muestra (ej. BT2F3).',
            'scientificName' => 'Nombre científico completo (ej. Morpho peleides).',
            'kingdom' => 'Reino (ej. Animalia).',
            'phylum' => 'Filo (ej. Arthropoda).',
            'class' => 'Clase (ej. Insecta).',
            'order' => 'Orden (ej. Lepidoptera).',
            'family' => 'Familia (ej. Nymphalidae).',
            'genus' => 'Género (ej. Morpho).',
            'specificEpithet' => 'Epíteto específico (ej. peleides).',
            'infraspecificEpithet' => 'Epíteto infraespecífico / subespecie (opcional).',
            'taxonRank' => 'Rango del nombre (ej. species, genus, family).',
            'recordedBy' => 'Colector(es) (ej. J. Pérez).',
            'eventDate' => 'Fecha de colecta. Admite fecha simple o rango (ej. 2001-02-14 o 14-26/feb/2001).',
            'country' => 'País (ej. Ecuador).',
            'stateProvince' => 'Provincia (ej. Orellana).',
            'municipality' => 'Cantón o municipio (ej. Aguarico).',
            'localityName' => 'Localidad (ej. Estación Científica Yasuní).',
            'decimalLatitude' => 'Latitud en grados decimales (ej. -0.6846).',
            'decimalLongitude' => 'Longitud en grados decimales (ej. -76.3969).',
            'geodeticDatum' => 'Datum geodésico (ej. WGS84).',
            'individualCount' => 'Número de individuos (entero).',
            'sex' => 'Sexo (ej. male, female).',
            'lifeStage' => 'Estadio de vida (ej. adult, larva).',
            'caste' => 'Casta, para insectos sociales (opcional).',
            'preparations' => 'Tipo de preparación (ej. pinned, alcohol).',
            'disposition' => 'Disposición del ejemplar (ej. in collection).',
            'occurrenceStatus' => 'Estado de la ocurrencia (present / absent).',
            'typeStatus' => 'Estatus de tipo (ej. holotype), si aplica.',
            'habitat' => 'Hábitat.',
            'biome' => 'Bioma.',
            'occurrenceRemarks' => 'Observaciones de la ocurrencia.',
            'taxonomicNotes' => 'Notas taxonómicas.',
            'specimenNotes' => 'Notas internas del espécimen.',
        ];
    }

    /** Devuelve el contenido binario del .xlsx de la plantilla. */
    public static function generar(): string
    {
        $columnas = self::columnas();
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
        $guia->fromArray([['Columna', 'Descripción y ejemplo']], null, 'A1');
        $fila = 2;
        foreach ($columnas as $nombre => $descripcion) {
            $guia->setCellValue("A{$fila}", $nombre);
            $guia->setCellValue("B{$fila}", $descripcion);
            $fila++;
        }
        $guia->getStyle('A1:B1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1B365D']],
        ]);
        $guia->getColumnDimension('A')->setAutoSize(true);
        $guia->getColumnDimension('B')->setWidth(90);
        $guia->getStyle('A1:B'.($fila - 1))->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

        $spreadsheet->setActiveSheetIndex(0);

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $contenido = (string) ob_get_clean();

        $spreadsheet->disconnectWorksheets();

        return $contenido;
    }
}
