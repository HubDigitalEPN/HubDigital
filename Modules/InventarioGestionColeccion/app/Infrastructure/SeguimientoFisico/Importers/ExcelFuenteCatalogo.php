<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers;

use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\Contracts\FuenteCatalogoIterator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Row;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Lee el catálogo directamente desde un archivo Excel (.xlsx / .xls), primera
 * fila = headers. Es el gemelo de `CsvFuenteCatalogo` para el flujo de carga por
 * la web: el curador sube el Excel tal cual, sin exportarlo a CSV antes.
 *
 * Streaming, no `toArray()`: se usa el lector en modo `setReadDataOnly(true)` y
 * se itera fila por fila con el `RowIterator`, acotando cada fila a las columnas
 * de la cabecera. Así el consumo de memoria no crece con el número de columnas
 * huérfanas de la hoja y el orchestrator recibe los mismos arrays asociativos
 * (header → valor) que ya recibe del CSV.
 *
 * Se leen los valores *formateados* (`getFormattedValue()`) para preservar los
 * verbatim tal como los ve el usuario (fechas, códigos), coherente con la regla
 * de "cero pérdida" del importador.
 */
final class ExcelFuenteCatalogo implements FuenteCatalogoIterator
{
    /** @var string[] */
    private array $headers = [];

    public function __construct(private readonly string $rutaArchivo)
    {
        if (! is_file($this->rutaArchivo)) {
            throw new \InvalidArgumentException("Archivo no encontrado: '{$this->rutaArchivo}'.");
        }
    }

    public function headers(): array
    {
        if ($this->headers === []) {
            $this->leerHeaders();
        }

        return $this->headers;
    }

    public function iterar(): \Generator
    {
        $hoja = $this->cargarHoja();
        $columnaFinal = $hoja->getHighestDataColumn();

        $numFila = 0;
        $primera = true;
        foreach ($hoja->getRowIterator() as $fila) {
            $valores = $this->leerFila($fila, $columnaFinal);

            if ($primera) {
                $primera = false;
                $this->headers = array_map(fn ($h) => (string) $h, $valores);

                continue;
            }

            // Ignora filas totalmente vacías (Excel suele arrastrar filas fantasma).
            if ($this->estaVacia($valores)) {
                continue;
            }

            $numFila++;
            yield $numFila => $this->combinar($valores);
        }
    }

    private function leerHeaders(): void
    {
        $hoja = $this->cargarHoja();
        $columnaFinal = $hoja->getHighestDataColumn();

        foreach ($hoja->getRowIterator() as $fila) {
            $valores = $this->leerFila($fila, $columnaFinal);
            $this->headers = array_map(fn ($h) => (string) $h, $valores);
            break;
        }
    }

    private function cargarHoja(): Worksheet
    {
        $reader = IOFactory::createReaderForFile($this->rutaArchivo);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($this->rutaArchivo);

        return $spreadsheet->getActiveSheet();
    }

    /**
     * Lee una fila acotada a las columnas de la cabecera, devolviendo los valores
     * formateados en orden.
     *
     * @return array<int, string>
     */
    private function leerFila(Row $fila, string $columnaFinal): array
    {
        $iterador = $fila->getCellIterator('A', $columnaFinal);
        $iterador->setIterateOnlyExistingCells(false);

        $valores = [];
        foreach ($iterador as $celda) {
            $valores[] = (string) $celda->getFormattedValue();
        }

        return $valores;
    }

    /** @param array<int, string> $valores */
    private function estaVacia(array $valores): bool
    {
        foreach ($valores as $valor) {
            if (trim($valor) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, string>  $valores
     * @return array<string, string>
     */
    private function combinar(array $valores): array
    {
        $resultado = [];
        foreach ($this->headers as $i => $clave) {
            $resultado[$clave] = $valores[$i] ?? '';
        }

        return $resultado;
    }
}
