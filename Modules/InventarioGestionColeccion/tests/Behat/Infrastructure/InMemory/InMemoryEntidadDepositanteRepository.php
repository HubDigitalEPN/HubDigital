<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\EntidadDepositante;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EntidadDepositanteRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EntidadDepositanteId;

final class InMemoryEntidadDepositanteRepository implements EntidadDepositanteRepositoryInterface
{
    /** @var array<string, EntidadDepositante> */
    private array $store = [];

    public function nextIdentity(): EntidadDepositanteId
    {
        return EntidadDepositanteId::generar();
    }

    public function guardar(EntidadDepositante $entidad): void
    {
        $this->store[(string) $entidad->id()] = $entidad;
    }

    public function buscarPorId(EntidadDepositanteId $id): ?EntidadDepositante
    {
        return $this->store[(string) $id] ?? null;
    }

    public function buscarPorNombre(string $nombre): ?EntidadDepositante
    {
        foreach ($this->store as $entidad) {
            if ($entidad->nombre() === $nombre) {
                return $entidad;
            }
        }

        return null;
    }

    /** @return EntidadDepositante[] */
    public function buscarTodas(): array
    {
        return array_values($this->store);
    }
}
