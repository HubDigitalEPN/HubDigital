<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\BuscarEspecimenes;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\TaxonRepositoryInterface;

final class BuscarEspecimenesHandler
{
    public function __construct(
        private readonly EspecimenRepositoryInterface $especimenRepo,
        private readonly TaxonRepositoryInterface $taxonRepo,
    ) {}

    public function handle(BuscarEspecimenesInput $input): BuscarEspecimenesOutput
    {
        $especimenes = match ($input->criterio) {
            'taxon' => $this->buscarPorNombreTaxon($input->valor),
            'localidad' => $this->especimenRepo->buscarPorLocalidad($input->valor),
            'estado' => $this->especimenRepo->buscarPorEstado($input->valor),
            default => [],
        };

        $taxonIds = array_values(array_unique(
            array_map(fn (Especimen $e) => $e->taxonId(), $especimenes)
        ));

        $taxonesMap = [];
        if ($taxonIds !== []) {
            foreach ($this->taxonRepo->buscarPorIds($taxonIds) as $taxon) {
                $taxonesMap[(string) $taxon->id()] = $taxon->nombreCientifico();
            }
        }

        return new BuscarEspecimenesOutput(
            items: array_map(fn (Especimen $e) => [
                'id' => (string) $e->id(),
                'codigoCatalogo' => $e->codigoCatalogo(),
                'taxonId' => $e->taxonId(),
                'taxonNombre' => $taxonesMap[$e->taxonId()] ?? $e->taxonId(),
                'localidad' => $e->localidad(),
                'fechaColecta' => $e->fechaColecta(),
                'colector' => $e->colector(),
                'estado' => $e->estado()->value,
            ], $especimenes)
        );
    }

    /** @return Especimen[] */
    private function buscarPorNombreTaxon(string $nombre): array
    {
        $taxones = $this->taxonRepo->buscarPorNombreContiene($nombre);

        if (empty($taxones)) {
            return [];
        }

        $taxonIds = array_map(fn ($t) => (string) $t->id(), $taxones);

        return $this->especimenRepo->buscarPorTaxonIds($taxonIds);
    }
}
