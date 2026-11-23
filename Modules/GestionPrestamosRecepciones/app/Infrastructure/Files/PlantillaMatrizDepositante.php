<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Files;

use App\Support\PlantillasLaboratorio;
use Modules\GestionPrestamosRecepciones\Application\Ports\EsquemaMatrizPort;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PlantillaMatrizDepositante
{
    public function __construct(private readonly EsquemaMatrizPort $esquemas) {}

    public function descargar(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $libro = $this->crear();
            try {
                (new Xlsx($libro))->save('php://output');
            } finally {
                $libro->disconnectWorksheets();
            }
        }, 'matriz-darwin-core.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function crear(): Spreadsheet
    {
        $libro = IOFactory::load(__DIR__.'/../../../resources/plantillas/matriz-depositantes-v3.xlsx');
        $hoja = $libro->getSheet(0);
        $base = json_decode(file_get_contents(__DIR__.'/../../../database/data/matriz-depositantes-v3.json'), true, 512, JSON_THROW_ON_ERROR);
        $camposBase = array_column($base['columnas'], 'campo');
        // Respetar las cabeceras y columnas adicionales del panel curatorial,
        // conservando los identificadores internos y su posición.
        $columnas = array_values(array_filter($this->esquemas->leer()['columnas'],
            static fn (array $columna): bool => $columna['plantilla_depositante'] ?? in_array($columna['campo'], $camposBase, true)));
        $originales = [];
        foreach ($camposBase as $i => $campo) {
            $originales[$campo]['ancho'] = $hoja->getColumnDimensionByColumn($i + 1)->getWidth();
            foreach ([1, 2, 3] as $fila) {
                $celda = $hoja->getCell([$i + 1, $fila]);
                $originales[$campo]['celdas'][$fila] = [$celda->getValue(), $celda->getDataType(), $celda->getXfIndex()];
            }
        }
        $altoPlantilla = Coordinate::columnIndexFromString($hoja->getHighestColumn());
        $columnasSobrantes = $altoPlantilla - count($columnas);
        if ($columnasSobrantes > 0) {
            $hoja->removeColumnByIndex(count($columnas) + 1, $columnasSobrantes);
        }

        $referenciaVisual = $originales[$camposBase[0]];
        $ejemplos = PlantillasLaboratorio::referencia()['ejemplos_depositantes'];
        foreach ($columnas as $i => $columna) {
            $original = $originales[$columna['campo']] ?? null;
            $hoja->getColumnDimensionByColumn($i + 1)->setWidth(($original ?? $referenciaVisual)['ancho']);
            foreach ([1, 2, 3] as $fila) {
                [$valor, $tipo, $estilo] = $original['celdas'][$fila] ?? [null, DataType::TYPE_NULL, $referenciaVisual['celdas'][$fila][2]];
                $hoja->getCell([$i + 1, $fila])->setValueExplicit($valor, $tipo)->setXfIndex($estilo);
            }
            $hoja->setCellValueExplicit([$i + 1, 1], $columna['cabecera'], DataType::TYPE_STRING);
            if ($original === null && isset($ejemplos[$columna['campo']])) {
                $hoja->setCellValueExplicit([$i + 1, 2], $ejemplos[$columna['campo']], DataType::TYPE_STRING);
            }
            // Conservar el texto enriquecido de la guía original cuando no cambió.
            if ((string) $hoja->getCell([$i + 1, 3])->getValue() !== $columna['descripcion']) {
                $hoja->setCellValueExplicit([$i + 1, 3], $columna['descripcion'], DataType::TYPE_STRING);
            }
            $color = match ($columna['requisito_guia'] ?? $columna['requisito']) {
                'obligatorio' => 'FFFFC000', 'recomendado' => 'FFFFFF00', default => 'FFFFFFFF',
            };
            if (in_array($columna['campo'], ['researchPermit', 'transportPermit'], true)) {
                $hoja->setCellValueExplicit([$i + 1, 3], 'Se completa con el permiso registrado en el expediente.', DataType::TYPE_STRING);
                $color = 'FFFFFFFF';
            }
            $hoja->getStyle([$i + 1, 1])->getFill()->setFillType('solid')->getStartColor()->setARGB($color);
        }
        $hoja->freezePane('A2');
        $libro->setActiveSheetIndex(0);

        return $libro;
    }
}
