<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarEdicionesMasivas;

final readonly class ListarEdicionesMasivasOutput
{
    /**
     * @param  list<array{
     *   id: string,
     *   resumen: string,
     *   campo: string,
     *   totalAfectados: int,
     *   actor: ?string,
     *   fecha: string,
     *   deshecha: bool
     * }>  $items
     */
    public function __construct(
        public array $items,
    ) {}
}
