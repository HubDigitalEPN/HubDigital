<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Visitante;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\VisitanteRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\VisitanteId;

final class InMemoryVisitanteRepository implements VisitanteRepositoryInterface
{
    /** @var array<string, Visitante> */
    private array $store = [];

    public function nextIdentity(): VisitanteId
    {
        return VisitanteId::generar();
    }

    public function guardar(Visitante $visitante): void
    {
        $this->store[(string) $visitante->id()] = $visitante;
    }

    public function buscarPorId(VisitanteId $id): ?Visitante
    {
        return $this->store[(string) $id] ?? null;
    }

    /** @return Visitante[] */
    public function buscarTodos(): array
    {
        return array_values($this->store);
    }
}
