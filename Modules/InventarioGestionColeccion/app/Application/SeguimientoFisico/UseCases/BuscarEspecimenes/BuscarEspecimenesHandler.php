<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\BuscarEspecimenes;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Taxon;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\TaxonRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services\MapeadorFilaEspecimen;
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
        $taxon = $this->resolverTaxon($input);
        if ($taxon === false) {
            // Se pidió filtro por FAMILIA y no hubo coincidencias: resultado vacío.
            // (El filtro por nombre no corta aquí: puede matchear taxon_verbatim.)
            return $this->salidaVacia($input);
        }

        $paginado = $input->page !== null;
        $perPage = max(1, $input->perPage);
        $paginaActual = $paginado ? max(1, $input->page) : 1;
        $limit = $paginado ? $perPage : $input->limit;
        $offset = $paginado ? ($paginaActual - 1) * $perPage : 0;

        $filtros = [
            'taxonIds' => $taxon['idsFamilia'] ?? null,
            'taxonNombreIds' => $taxon['idsNombre'] ?? null,
            'taxonNombreTexto' => $taxon['texto'] ?? null,
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
    /**
     * Resuelve el filtro taxonómico en tres piezas independientes:
     *  - idsFamilia: taxones (familia + descendientes) para un match ESTRICTO por
     *    taxon_id. Si se pidió familia y no coincide ninguna, devuelve false.
     *  - idsNombre:  taxones (coincidencia por nombre + descendientes) para los
     *    especímenes ya enlazados a un Taxón.
     *  - texto:      el texto del filtro por nombre, para también matchear
     *    `taxon_verbatim` en especímenes cuya taxonomía NO está enlazada a un
     *    Taxón (caso frecuente tras una importación).
     *
     * @return array{idsFamilia?: string[], idsNombre?: string[], texto?: string}|false
     */
    private function resolverTaxon(BuscarEspecimenesInput $input): array|false
    {
        $out = [];

        if ($input->familia !== null && trim($input->familia) !== '') {
            $familiaCandidatos = array_filter(
                $this->taxonRepo->buscarPorNombreContiene($input->familia),
                fn (Taxon $t) => $t->rango() === RangoTaxonomico::Familia,
            );
            if ($familiaCandidatos === []) {
                return false;
            }
            $idsFamilia = $this->taxonRepo->listarDescendientesIdsDeVarios(
                array_map(fn (Taxon $t) => (string) $t->id(), $familiaCandidatos),
            );
            if ($idsFamilia === []) {
                return false;
            }
            $out['idsFamilia'] = $idsFamilia;
        }

        if ($input->taxonNombre !== null && trim($input->taxonNombre) !== '') {
            // Coincidencias por entidad Taxón (+ descendientes), para que un rango
            // alto (reino, orden, familia…) traiga todo lo que cuelga debajo.
            // Resolución por lotes: un solo CTE para todos los taxa coincidentes,
            // en vez de uno por match (que colgaba con términos genéricos).
            $raices = array_map(
                fn (Taxon $t) => (string) $t->id(),
                $this->taxonRepo->buscarPorNombreContiene($input->taxonNombre),
            );
            $out['idsNombre'] = $this->taxonRepo->listarDescendientesIdsDeVarios($raices);
            $out['texto'] = trim($input->taxonNombre);
        }

        return $out;
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

        return array_map(
            fn (Especimen $e) => MapeadorFilaEspecimen::mapear(
                $e,
                $e->taxonId() !== null ? ($taxonesMap[$e->taxonId()] ?? null) : null,
            ),
            $especimenes,
        );
    }
}
