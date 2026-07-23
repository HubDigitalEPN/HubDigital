<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ExportarEspecimenesSeleccionados;

final readonly class ExportarEspecimenesSeleccionadosOutput
{
    /**
     * @param  string[]  $columnas  Encabezados en orden.
     * @param  array<int, array<string, string>>  $filas  Una fila por espécimen (columna → valor).
     */
    public function __construct(
        public array $columnas,
        public array $filas,
    ) {}
}
