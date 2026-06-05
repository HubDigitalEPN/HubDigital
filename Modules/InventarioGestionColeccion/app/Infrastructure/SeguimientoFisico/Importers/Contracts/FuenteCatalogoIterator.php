<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\Contracts;

/**
 * Abstracción sobre la fuente del catálogo (CSV, Excel exportado, etc.).
 *
 * El orchestrator no conoce el formato; sólo recibe arrays asociativos
 * por fila (header → valor).
 */
interface FuenteCatalogoIterator
{
    /**
     * Itera filas como array asociativo header → valor.
     *
     * @return \Generator<int, array<string, string>>
     */
    public function iterar(): \Generator;

    /**
     * Devuelve los headers detectados en la fuente.
     *
     * @return string[]
     */
    public function headers(): array;
}
