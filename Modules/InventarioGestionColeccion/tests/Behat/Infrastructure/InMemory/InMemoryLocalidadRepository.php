<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Localidad;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\LocalidadRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\LocalidadId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\RangoLocalidad;

final class InMemoryLocalidadRepository implements LocalidadRepositoryInterface
{
    /** @var array<string, Localidad> */
    private array $store = [];

    public function nextIdentity(): LocalidadId
    {
        return LocalidadId::generar();
    }

    public function guardar(Localidad $localidad): void
    {
        $this->store[(string) $localidad->id()] = $localidad;
    }

    public function buscarPorId(LocalidadId $id): ?Localidad
    {
        return $this->store[(string) $id] ?? null;
    }

    public function buscarPorNombreYRango(string $nombreCanonico, RangoLocalidad $rango): ?Localidad
    {
        foreach ($this->store as $l) {
            if ($l->nombreCanonico() === $nombreCanonico && $l->rango()->equals($rango)) {
                return $l;
            }
        }

        return null;
    }

    /** @return Localidad[] */
    public function buscarPorNombreContiene(string $fragmento): array
    {
        $items = array_values(array_filter(
            $this->store,
            fn (Localidad $l) => stripos($l->nombreCanonico(), $fragmento) !== false
        ));
        usort($items, fn (Localidad $a, Localidad $b) => strcmp($a->nombreCanonico(), $b->nombreCanonico()));

        return $items;
    }

    /** @return Localidad[] */
    public function buscarTodos(): array
    {
        $items = array_values($this->store);
        usort($items, fn (Localidad $a, Localidad $b) => strcmp($a->nombreCanonico(), $b->nombreCanonico()));

        return $items;
    }

    public function contarTodos(): int
    {
        return count($this->store);
    }

    /** @return Localidad[] */
    public function listarPagina(int $offset, int $limit): array
    {
        return array_slice($this->buscarTodos(), max(0, $offset), max(1, $limit));
    }

    /**
     * @param  string[]  $ids
     * @return Localidad[]
     */
    public function buscarPorIds(array $ids): array
    {
        return array_values(array_filter(
            $this->store,
            fn (Localidad $l) => in_array((string) $l->id(), $ids, true)
        ));
    }
}
