<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Taxon;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\TaxonRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\RangoTaxonomico;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TaxonId;

final class InMemoryTaxonRepository implements TaxonRepositoryInterface
{
    /** @var array<string, Taxon> */
    private array $store = [];

    public function nextIdentity(): TaxonId
    {
        return TaxonId::generar();
    }

    public function guardar(Taxon $taxon): void
    {
        $this->store[(string) $taxon->id()] = $taxon;
    }

    public function buscarPorId(TaxonId $id): ?Taxon
    {
        return $this->store[(string) $id] ?? null;
    }

    public function buscarPorNombreYRango(string $nombreCientifico, RangoTaxonomico $rango): ?Taxon
    {
        foreach ($this->store as $taxon) {
            if ($taxon->nombreCientifico() === $nombreCientifico && $taxon->rango()->equals($rango)) {
                return $taxon;
            }
        }

        return null;
    }

    /** @return Taxon[] */
    public function buscarPorNombreContiene(string $nombre): array
    {
        return array_values(array_filter(
            $this->store,
            fn (Taxon $t) => stripos($t->nombreCientifico(), $nombre) !== false
        ));
    }

    /** @return Taxon[] */
    public function buscarTodos(): array
    {
        return array_values($this->store);
    }

    /**
     * @param  string[]  $ids
     * @return Taxon[]
     */
    public function buscarPorIds(array $ids): array
    {
        return array_values(array_filter(
            $this->store,
            fn (Taxon $t) => in_array((string) $t->id(), $ids, true)
        ));
    }
}
