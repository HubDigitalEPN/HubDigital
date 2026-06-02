<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarEspecimenesAsignables;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\TaxonRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UnitTrayEspecimenRepository;

final class ListarEspecimenesAsignablesHandler
{
    public function __construct(
        private readonly EspecimenRepositoryInterface $especimenRepo,
        private readonly TaxonRepositoryInterface $taxonRepo,
        private readonly UnitTrayEspecimenRepository $asignacionRepo,
    ) {}

    public function handle(): ListarEspecimenesAsignablesOutput
    {
        $especimenes = $this->especimenRepo->buscarTodos();

        $taxonIds = array_values(array_unique(
            array_map(fn (Especimen $e) => $e->taxonId(), $especimenes)
        ));

        $nombresPorTaxon = [];
        if ($taxonIds !== []) {
            foreach ($this->taxonRepo->buscarPorIds($taxonIds) as $taxon) {
                $nombresPorTaxon[(string) $taxon->id()] = $taxon->nombreCientifico();
            }
        }

        $items = array_map(function (Especimen $e) use ($nombresPorTaxon): array {
            $unitTrayId = $this->asignacionRepo->unitTrayDeEspecimen((string) $e->id());

            return [
                'id' => (string) $e->id(),
                'codigoCatalogo' => $e->codigoCatalogo(),
                'taxonNombre' => $nombresPorTaxon[$e->taxonId()] ?? $e->taxonId(),
                'unitTrayId' => $unitTrayId !== null ? (string) $unitTrayId : null,
            ];
        }, $especimenes);

        return new ListarEspecimenesAsignablesOutput(items: $items);
    }
}
