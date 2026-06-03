<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarPermisosDeEspecimen;

final readonly class ListarPermisosDeEspecimenOutput
{
    /**
     * @param  list<array{id:string, tipo:string, numero:?string, responsable:?string, detalles:?string}>  $items
     */
    public function __construct(
        public string $especimenId,
        public array $items,
    ) {}
}
