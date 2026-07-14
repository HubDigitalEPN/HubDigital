<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarEspecimenesDeMuestra;

final readonly class ListarEspecimenesDeMuestraOutput
{
    /**
     * @param  array<int, array{
     *   id: string,
     *   codigoCatalogo: string,
     *   taxonNombre: ?string,
     *   localidad: string,
     *   fechaColecta: string,
     *   colector: string
     * }>  $items
     */
    public function __construct(
        public array $items,
    ) {}
}
