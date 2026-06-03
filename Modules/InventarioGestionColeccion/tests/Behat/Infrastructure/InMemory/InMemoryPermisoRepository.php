<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Permiso;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\PermisoRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\PermisoId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TipoPermiso;

final class InMemoryPermisoRepository implements PermisoRepositoryInterface
{
    /** @var array<string, Permiso> */
    private array $store = [];

    public function nextIdentity(): PermisoId
    {
        return PermisoId::generar();
    }

    public function guardar(Permiso $permiso): void
    {
        $this->store[(string) $permiso->id()] = $permiso;
    }

    public function buscarPorId(PermisoId $id): ?Permiso
    {
        return $this->store[(string) $id] ?? null;
    }

    /** @return Permiso[] */
    public function buscarPorTipo(TipoPermiso $tipo): array
    {
        return array_values(array_filter(
            $this->store,
            fn (Permiso $p) => $p->tipo()->equals($tipo)
        ));
    }

    /** @return Permiso[] */
    public function buscarTodos(): array
    {
        return array_values($this->store);
    }

    public function contarTodos(): int
    {
        return count($this->store);
    }
}
