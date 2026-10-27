<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Files;

use Modules\GestionPrestamosRecepciones\Application\Ports\EsquemaMatrizPort;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

final class PlantillaMatrizDepositante
{
    public function __construct(private readonly EsquemaMatrizPort $esquemas) {}

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
        foreach ($columnas as $i => $columna) {
            $hoja->setCellValueExplicit([$i + 1, 1], $columna['cabecera'], DataType::TYPE_STRING);
            // Conservar el texto enriquecido de la guía original cuando no cambió.
            if ((string) $hoja->getCell([$i + 1, 3])->getValue() !== $columna['descripcion']) {
                $hoja->setCellValueExplicit([$i + 1, 3], $columna['descripcion'], DataType::TYPE_STRING);
            }
            $color = match ($columna['requisito']) { 'obligatorio' => 'FFFFC000', 'recomendado' => 'FFFFFF00', default => 'FFFFFFFF' };
            $hoja->getStyle([$i + 1, 1])->getFill()->setFillType('solid')->getStartColor()->setARGB($color);
        }
        $hoja->freezePane('A2');
        $libro->setActiveSheetIndex(0);

        return $libro;
    }
}
