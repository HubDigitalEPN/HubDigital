<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Files;

use DomainException;
use Modules\GestionPrestamosRecepciones\Domain\Services\EsquemaMatriz;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use ZipArchive;

final class ImportadorPlantillaDepositos
{
    private const MAX_COLUMNAS = 500;

    private const MAX_FILAS = 30;

    public function leer(string $ruta, array $actual): array
    {
        $this->validarArchivo($ruta);
        $lector = new Xlsx;
        $lector->setLoadSheetsOnly($lector->listWorksheetNames($ruta)[0] ?? throw new DomainException('El Excel no contiene una hoja.'));
        $lector->setReadFilter(new class(self::MAX_FILAS + 1) implements IReadFilter
        {
            public function __construct(private readonly int $limite) {}

            public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
            {
                return $row <= $this->limite;
            }
        });
        $libro = $lector->load($ruta);

        try {
            $hoja = $libro->getSheet(0);
            $ancho = Coordinate::columnIndexFromString($hoja->getHighestDataColumn());
            if ($ancho > self::MAX_COLUMNAS || $hoja->getHighestDataRow() > self::MAX_FILAS) {
                throw new DomainException('Usa una plantilla de configuración con un máximo de 500 columnas y 30 filas.');
            }

            $guia = null;
            for ($fila = 4; $fila <= $hoja->getHighestDataRow() - 3; $fila++) {
                if (EsquemaMatriz::clave((string) $hoja->getCell([1, $fila])->getValue()) === 'clave'
                    && str_contains(EsquemaMatriz::clave((string) $hoja->getCell([1, $fila + 1])->getValue()), 'obligatorio')
                    && str_contains(EsquemaMatriz::clave((string) $hoja->getCell([1, $fila + 3])->getValue()), 'opcional')) {
                    $guia = $fila - 2;
                    break;
                }
            }
            if ($guia === null) {
                throw new DomainException('La plantilla debe incluir la fila de descripciones y la clave de colores. Puedes partir del Excel que se descarga en esta pantalla.');
            }

            $mapa = EsquemaMatriz::mapa($actual['columnas']);
            $columnas = [];
            $nuevas = 0;
            for ($i = 1; $i <= $ancho; $i++) {
                $celda = $hoja->getCell([$i, 1]);
                $cabecera = $this->texto($celda, 160);
                if ($cabecera === '') {
                    if ($this->texto($hoja->getCell([$i, $guia]), 4000) !== '') {
                        throw new DomainException('Cada descripción debe tener su cabecera en la primera fila.');
                    }
                    continue;
                }
                $anterior = $mapa[EsquemaMatriz::clave($cabecera)] ?? null;
                $campo = $anterior['campo'] ?? $cabecera;
                if (isset($columnas[$campo])) {
                    throw new DomainException("La cabecera «{$cabecera}» está repetida o coincide con un alias de otra cabecera.");
                }

                $fill = $hoja->getStyle($celda->getCoordinate())->getFill();
                $color = $fill->getFillType() === Fill::FILL_SOLID ? $fill->getStartColor()->getRGB() : 'FFFFFF';
                $requisito = match ($color) {
                    'FFC000' => 'obligatorio',
                    'FFFF00' => 'recomendado',
                    'FFFFFF' => 'opcional',
                    default => throw new DomainException("La cabecera «{$cabecera}» debe usar uno de los colores de la clave."),
                };
                $descripcion = $this->texto($hoja->getCell([$i, $guia]), 4000);
                $alias = $anterior['alias'] ?? [];
                if ($anterior !== null && $anterior['cabecera'] !== $cabecera) {
                    $alias[] = $anterior['cabecera'];
                }
                if ($anterior !== null && (in_array($campo, ['researchPermit', 'transportPermit'], true)
                    || in_array($anterior['requisito'], ['interno', 'predeterminado'], true)
                    || $requisito === ($anterior['requisito_guia'] ?? null))) {
                    $requisito = $anterior['requisito'];
                } elseif (isset($anterior['requisito_guia'])) {
                    unset($anterior['requisito_guia']);
                }
                if ($anterior !== null && $descripcion !== ($anterior['descripcion'] ?? '') && ($anterior['descripcion'] ?? '') !== '') {
                    $actual['descripciones_guia'][$campo] = array_values(array_unique([
                        ...($actual['descripciones_guia'][$campo] ?? []), $anterior['descripcion'],
                    ]));
                }
                $columnas[$campo] = [
                    'campo' => $campo,
                    'cabecera' => $cabecera,
                    'alias' => array_values(array_unique($alias)),
                    'descripcion' => $descripcion,
                    'requisito' => $requisito,
                    'plantilla_depositante' => true,
                ] + ($anterior ?? ['formato' => 'texto']);
                $nuevas += (int) ($anterior === null);
            }
            if ($columnas === []) {
                throw new DomainException('La primera fila debe contener las cabeceras de la plantilla.');
            }
            $visibles = count($columnas);
            $ocultas = 0;
            foreach ($actual['columnas'] as $columna) {
                if (isset($columnas[$columna['campo']])) {
                    continue;
                }
                if (($columna['plantilla_depositante'] ?? false) && $columna['requisito'] === 'obligatorio') {
                    throw new DomainException("Falta la cabecera obligatoria «{$columna['cabecera']}». Inclúyela o cambia su requisito en Matrices antes de retirarla.");
                }
                $ocultas += (int) ($columna['plantilla_depositante'] ?? false);
                $columnas[$columna['campo']] = ['plantilla_depositante' => false] + $columna;
            }

            $actual['columnas'] = array_values($columnas);
            EsquemaMatriz::mapa($actual['columnas']);

            return ['definicion' => $actual, 'visibles' => $visibles, 'nuevas' => $nuevas, 'ocultas' => $ocultas];
        } finally {
            $libro->disconnectWorksheets();
        }
    }

    private function texto(Cell $celda, int $limite): string
    {
        if ($celda->getDataType() === DataType::TYPE_FORMULA) {
            throw new DomainException('Las cabeceras y las descripciones de la plantilla no admiten fórmulas.');
        }
        $texto = trim((string) $celda->getValue());
        if (mb_strlen($texto) > $limite) {
            throw new DomainException("La celda {$celda->getCoordinate()} supera el máximo de {$limite} caracteres.");
        }

        return $texto;
    }

    private function validarArchivo(string $ruta): void
    {
        $zip = new ZipArchive;
        if ($zip->open($ruta) !== true) {
            throw new DomainException('Selecciona una plantilla .xlsx válida.');
        }
        try {
            $bytes = 0;
            if ($zip->numFiles > 500) {
                throw new DomainException('La plantilla contiene demasiados componentes.');
            }
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entrada = $zip->statIndex($i);
                $bytes += $entrada['size'];
                if ($bytes > 16 * 1024 * 1024) {
                    throw new DomainException('La plantilla excede el tamaño permitido.');
                }
            }
        } finally {
            $zip->close();
        }
    }
}
