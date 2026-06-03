<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\BuscarEspecimenes;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\TaxonRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TipoIdentificadorEspecimen;

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
            'codigo' => $this->especimenRepo->buscarPorIdentificador(
                TipoIdentificadorEspecimen::CodigoCatalogo->value,
                $input->valor,
            ),
            'occurrence_id' => $this->especimenRepo->buscarPorIdentificador(
                TipoIdentificadorEspecimen::OccurrenceId->value,
                $input->valor,
            ),
            'catalog_number' => $this->especimenRepo->buscarPorIdentificador(
                TipoIdentificadorEspecimen::CatalogNumber->value,
                $input->valor,
            ),
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
                'occurrenceId' => $e->occurrenceId(),
                'catalogNumber' => $e->catalogNumber(),
                'oldCode' => $e->oldCode(),
                'cardexLiquidCollectionCode' => $e->cardexLiquidCollectionCode(),
                'individualCount' => $e->individualCount(),
                'preparations' => $e->preparations(),
                'disposition' => $e->disposition(),
                'occurrenceStatus' => $e->occurrenceStatus(),
                'specimenNotes' => $e->specimenNotes(),
                'country' => $e->country(),
                'stateProvince' => $e->stateProvince(),
                'municipality' => $e->municipality(),
                'localityName' => $e->localityName(),
                'decimalLatitude' => $e->decimalLatitude(),
                'decimalLongitude' => $e->decimalLongitude(),
                'geodeticDatum' => $e->geodeticDatum(),
                'elevationMinM' => $e->elevationMinM(),
                'biome' => $e->biome(),
                'habitat' => $e->habitat(),
                'identificadores' => array_map(fn ($i) => $i->toArray(), $e->identificadores()),
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
