<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\BuscarEspecimenes;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Taxon;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\TaxonRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\RangoTaxonomico;

/**
 * Búsqueda multi-filtro de especímenes. Combina campos opcionales con AND.
 *
 * Resoluciones especiales:
 *  - `taxonNombre`: traduce a IDs de taxa cuyo nombre contiene la cadena.
 *  - `familia`: encuentra taxa con rango=familia + nombre contiene, expande
 *    a todos sus descendientes (vía CTE recursivo), y filtra por esos IDs.
 *  - Si `taxonNombre` Y `familia` se especifican, se intersectan los sets
 *    de IDs.
 */
final class BuscarEspecimenesHandler
{
    public function __construct(
        private readonly EspecimenRepositoryInterface $especimenRepo,
        private readonly TaxonRepositoryInterface $taxonRepo,
    ) {}

    public function handle(BuscarEspecimenesInput $input): BuscarEspecimenesOutput
    {
        if (! $input->tieneFiltros()) {
            // Sin filtros, devolvemos vacío para evitar cargar miles de filas.
            return $this->salidaVacia($input);
        }

        // Resolver taxa por nombre y/o familia.
        $taxonIds = $this->resolverTaxonIds($input);
        if ($taxonIds === false) {
            // Se pidió filtro taxonómico pero no hay matches: resultado vacío.
            return $this->salidaVacia($input);
        }

        $paginado = $input->page !== null;
        $perPage = max(1, $input->perPage);
        $paginaActual = $paginado ? max(1, $input->page) : 1;
        $limit = $paginado ? $perPage : $input->limit;
        $offset = $paginado ? ($paginaActual - 1) * $perPage : 0;

        $filtros = [
            'taxonIds' => $taxonIds,
            'codigoCatalogo' => $input->codigoCatalogo,
            'occurrenceId' => $input->occurrenceId,
            'catalogNumber' => $input->catalogNumber,
            'localidad' => $input->localidad,
            'colector' => $input->colector,
            'fechaDesde' => $input->fechaDesde,
            'fechaHasta' => $input->fechaHasta,
            'estado' => $input->estado,
            'estadoRevision' => $input->estadoRevision,
            'motivoRevision' => $input->motivoRevision,
            'paraRevision' => $input->paraRevision,
            'limit' => $limit,
            'offset' => $offset,
        ];
        // Limpia las claves null para que el repo no filtre por ellas. `offset=0`
        // es un valor válido y array_filter lo conserva (0 !== null).
        $filtros = array_filter($filtros, fn ($v) => $v !== null && $v !== '' && $v !== []);

        $especimenes = $this->especimenRepo->buscarConFiltros($filtros);

        if ($paginado) {
            $total = $this->especimenRepo->contarConFiltros($filtros);
            $totalPaginas = $total > 0 ? (int) ceil($total / $perPage) : 1;

            return new BuscarEspecimenesOutput(
                items: $this->mapearItems($especimenes),
                total: $total,
                page: $paginaActual,
                perPage: $perPage,
                totalPaginas: $totalPaginas,
            );
        }

        return new BuscarEspecimenesOutput(
            items: $this->mapearItems($especimenes),
            total: count($especimenes),
            page: 1,
            perPage: $limit,
            totalPaginas: 1,
        );
    }

    private function salidaVacia(BuscarEspecimenesInput $input): BuscarEspecimenesOutput
    {
        $paginado = $input->page !== null;

        return new BuscarEspecimenesOutput(
            items: [],
            total: 0,
            page: $paginado ? max(1, $input->page) : 1,
            perPage: $paginado ? max(1, $input->perPage) : $input->limit,
            totalPaginas: 1,
        );
    }

    /**
     * Resuelve los IDs de taxa según `taxonNombre` y `familia`. Retorna:
     *  - null si no se pidió filtro taxonómico (no aplicar).
     *  - false si se pidió filtro pero no hay coincidencias (resultado vacío).
     *  - array de IDs si hay matches.
     *
     * @return string[]|null|false
     */
    private function resolverTaxonIds(BuscarEspecimenesInput $input): array|null|false
    {
        $idsTaxonNombre = null;
        if ($input->taxonNombre !== null && trim($input->taxonNombre) !== '') {
            $taxa = $this->taxonRepo->buscarPorNombreContiene($input->taxonNombre);
            $idsTaxonNombre = array_map(fn (Taxon $t) => (string) $t->id(), $taxa);
            if ($idsTaxonNombre === []) {
                return false;
            }
        }

        $idsFamilia = null;
        if ($input->familia !== null && trim($input->familia) !== '') {
            $familiaCandidatos = array_filter(
                $this->taxonRepo->buscarPorNombreContiene($input->familia),
                fn (Taxon $t) => $t->rango() === RangoTaxonomico::Familia,
            );
            if ($familiaCandidatos === []) {
                return false;
            }
            $idsFamilia = [];
            foreach ($familiaCandidatos as $t) {
                $idsFamilia = array_merge($idsFamilia, $this->taxonRepo->listarDescendientesIds((string) $t->id()));
            }
            $idsFamilia = array_values(array_unique($idsFamilia));
            if ($idsFamilia === []) {
                return false;
            }
        }

        if ($idsTaxonNombre !== null && $idsFamilia !== null) {
            $intersec = array_values(array_intersect($idsTaxonNombre, $idsFamilia));

            return $intersec === [] ? false : $intersec;
        }

        return $idsTaxonNombre ?? $idsFamilia;
    }

    /**
     * @param  Especimen[]  $especimenes
     * @return list<array<string, mixed>>
     */
    private function mapearItems(array $especimenes): array
    {
        $taxonIds = array_values(array_unique(array_filter(
            array_map(fn (Especimen $e) => $e->taxonId(), $especimenes)
        )));

        $taxonesMap = [];
        if ($taxonIds !== []) {
            foreach ($this->taxonRepo->buscarPorIds($taxonIds) as $taxon) {
                $taxonesMap[(string) $taxon->id()] = $taxon->nombreCientifico();
            }
        }

        return array_map(fn (Especimen $e) => [
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
            'entidadDepositanteId' => $e->entidadDepositanteId(),
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
        ], $especimenes);
    }
}
