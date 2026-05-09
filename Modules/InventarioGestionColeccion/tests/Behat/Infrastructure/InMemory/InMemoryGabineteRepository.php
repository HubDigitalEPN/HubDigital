<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Gabinete;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\GabineteRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\GabineteId;

final class InMemoryGabineteRepository implements GabineteRepository
{
    /** @var array<string, Gabinete> */
    private array $store = [];

    public function nextIdentity(): GabineteId
    {
        return GabineteId::generar();
    }

    public function guardar(Gabinete $gabinete): void
    {
        $this->store[(string) $gabinete->id()] = $gabinete;
    }

    public function buscarPorId(GabineteId $id): ?Gabinete
    {
        return $this->store[(string) $id] ?? null;
    }

    /** @return Gabinete[] */
    public function buscarActivos(): array
    {
        return array_values(array_filter($this->store, fn (Gabinete $g) => $g->activo()));
    }
}
