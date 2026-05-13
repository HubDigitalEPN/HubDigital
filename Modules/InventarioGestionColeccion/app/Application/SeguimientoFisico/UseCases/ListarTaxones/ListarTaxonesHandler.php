<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarTaxones;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Taxon;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\TaxonRepositoryInterface;

final class ListarTaxonesHandler
{
    public function __construct(
        private readonly TaxonRepositoryInterface $taxonRepo,
    ) {}

    public function handle(): ListarTaxonesOutput
    {
        $taxones = $this->taxonRepo->buscarTodos();

        $items = array_map(
            fn (Taxon $t) => new ListarTaxonesItemOutput(
                id: (string) $t->id(),
                nombreCientifico: $t->nombreCientifico(),
                rango: $t->rango()->value,
                autor: $t->autor(),
                anioDescripcion: $t->anioDescripcion(),
                estado: $t->estado()->value,
                padreId: $t->padreId() !== null ? (string) $t->padreId() : null,
            ),
            $taxones,
        );

        return new ListarTaxonesOutput($items);
    }
}
