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
}
