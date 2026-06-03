<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers;

use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\Contracts\FuenteCatalogoIterator;

/**
 * Lee el catálogo como CSV (UTF-8, primera fila = headers).
 *
 * El Excel original (~48.856 filas) debe exportarse a CSV antes del import.
 * Esto evita una dependencia de phpoffice/phpspreadsheet y permite procesar
 * el archivo en streaming sin cargar todo a memoria.
 *
 * Nota: usa SplFileObject con CSV_FLAGS para manejar comillas, saltos de
 * línea dentro de celdas y separadores configurables.
 */
final class CsvFuenteCatalogo implements FuenteCatalogoIterator
{
    /** @var string[] */
    private array $headers = [];

    public function __construct(
        private readonly string $rutaArchivo,
        private readonly string $delimitador = ',',
        private readonly string $encerrador = '"',
    ) {
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
        $this->headers();
        $file = new \SplFileObject($this->rutaArchivo, 'r');
        $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::READ_AHEAD
            | \SplFileObject::SKIP_EMPTY | \SplFileObject::DROP_NEW_LINE);
        // PHP 8.4 deprecation: $escape se debe pasar explícitamente.
        $file->setCsvControl($this->delimitador, $this->encerrador, '\\');

        $primero = true;
        $numFila = 0;
        foreach ($file as $linea) {
            if ($primero) {
                $primero = false;

                continue;
            }
            if ($linea === null || $linea === [null]) {
                continue;
            }
            $numFila++;
            $fila = $this->combinar($linea);
            yield $numFila => $fila;
        }
    }

    /** @param array<int, string|null> $valores */
    private function combinar(array $valores): array
    {
        $resultado = [];
        foreach ($this->headers as $i => $clave) {
            $resultado[$clave] = $valores[$i] ?? '';
        }

        return $resultado;
    }

    private function leerHeaders(): void
    {
        $file = new \SplFileObject($this->rutaArchivo, 'r');
        $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::DROP_NEW_LINE);
        $file->setCsvControl($this->delimitador, $this->encerrador, '\\');
        $first = $file->fgetcsv($this->delimitador, $this->encerrador, '\\');
        if (! is_array($first)) {
            throw new \RuntimeException("CSV vacío o no legible: '{$this->rutaArchivo}'.");
        }
        $this->headers = array_map(fn ($h) => (string) $h, $first);
    }
}
