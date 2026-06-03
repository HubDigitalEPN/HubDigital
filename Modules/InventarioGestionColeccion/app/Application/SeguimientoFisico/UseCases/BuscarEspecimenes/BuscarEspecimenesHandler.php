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
            'para_revision' => $this->especimenRepo->buscarParaRevision(
                $input->valor !== '' ? $input->valor : null,
            ),
            default => [],
        };

        $taxonIds = array_values(array_unique(array_filter(
            array_map(fn (Especimen $e) => $e->taxonId(), $especimenes)
        )));

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
                'taxonNombre' => $e->taxonId() !== null ? ($taxonesMap[$e->taxonId()] ?? $e->taxonId()) : null,
                'taxonVerbatim' => $e->taxonVerbatim(),
                'localidad' => $e->localidad(),
                'localidadVerbatim' => $e->localidadVerbatim(),
                'fechaColecta' => $e->fechaColecta(),
                'fechaColectaFin' => $e->fechaColectaFin(),
                'fechaVerbatim' => $e->fechaVerbatim(),
                'colector' => $e->colector(),
                'estado' => $e->estado()->value,
                'occurrenceId' => $e->occurrenceId(),
                'catalogNumber' => $e->catalogNumber(),
                'oldCode' => $e->oldCode(),
                'cardexLiquidCollectionCode' => $e->cardexLiquidCollectionCode(),
                'individualCount' => $e->individualCount(),
                'individualCountVerbatim' => $e->individualCountVerbatim(),
                'sex' => $e->sex(),
                'lifeStage' => $e->lifeStage(),
                'caste' => $e->caste(),
                'typeStatus' => $e->typeStatus(),
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
                'coordVerbatim' => $e->coordVerbatim(),
                'geodeticDatum' => $e->geodeticDatum(),
                'elevationMinM' => $e->elevationMinM(),
                'elevationMaxM' => $e->elevationMaxM(),
                'biome' => $e->biome(),
                'habitat' => $e->habitat(),
                'microhabitat' => $e->microhabitat(),
                'biogeographicRegion' => $e->biogeographicRegion(),
                'endemic' => $e->endemic(),
                'dnaNotes' => $e->dnaNotes(),
                'occurrenceRemarks' => $e->occurrenceRemarks(),
                'taxonomicNotes' => $e->taxonomicNotes(),
                'actaRecepcion' => $e->actaRecepcion(),
                'estadoRevision' => $e->estadoRevision()->value,
                'motivoRevision' => $e->motivoRevision(),
                'filaOrigenExcel' => $e->filaOrigenExcel(),
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
