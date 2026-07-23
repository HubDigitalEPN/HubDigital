<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Taxon;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\RangoTaxonomico;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TaxonId;

interface TaxonRepositoryInterface
{
    public function nextIdentity(): TaxonId;

    public function guardar(Taxon $taxon): void;

    public function buscarPorId(TaxonId $id): ?Taxon;

    public function buscarPorNombreYRango(string $nombreCientifico, RangoTaxonomico $rango): ?Taxon;

    /** @return Taxon[] */
    public function buscarPorNombreContiene(string $nombre): array;

    /** @return Taxon[] */
    public function buscarTodos(): array;

    /**
     * @param  string[]  $ids
     * @return Taxon[]
     */
    public function buscarPorIds(array $ids): array;

    /**
     * Devuelve los IDs (string) de todos los descendientes recursivos de un
     * taxón (incluyéndolo). Útil para filtrar especímenes "por familia"
     * resolviéndola a sus géneros y especies hijos.
     *
     * @return string[]
     */
    public function listarDescendientesIds(string $taxonId): array;

    /**
     * Versión por lotes de {@see listarDescendientesIds}: resuelve en UNA sola
     * consulta los descendientes recursivos de varios taxones raíz (incluyéndolos).
     * Evita el N+1 de llamar al CTE recursivo una vez por cada taxón coincidente
     * cuando el filtro por nombre matchea cientos/miles de taxa.
     *
     * @param  string[]  $taxonIds
     * @return string[]
     */
    public function listarDescendientesIdsDeVarios(array $taxonIds): array;
}
