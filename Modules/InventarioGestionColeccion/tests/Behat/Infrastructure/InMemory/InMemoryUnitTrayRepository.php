<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\UnitTray;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UnitTrayRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\UnitTrayId;

final class InMemoryUnitTrayRepository implements UnitTrayRepository
{
    /** @var array<string, UnitTray> */
    private array $store = [];

    public function nextIdentity(): UnitTrayId
    {
        return UnitTrayId::generar();
    }

    public function siguienteNumero(CajaId $cajaId): int
    {
        $numeros = array_map(
            fn (UnitTray $tray) => $tray->numero(),
            $this->buscarPorCaja($cajaId),
        );

        return $numeros === [] ? 1 : max($numeros) + 1;
    }

    public function guardar(UnitTray $unitTray): void
    {
        $this->store[(string) $unitTray->id()] = $unitTray;
    }

    public function buscarPorId(UnitTrayId $id): ?UnitTray
    {
        return $this->store[(string) $id] ?? null;
    }

    /** @return UnitTray[] */
    public function buscarPorCaja(CajaId $cajaId): array
    {
        return array_values(array_filter(
            $this->store,
            fn (UnitTray $tray) => $tray->cajaId()->equals($cajaId),
        ));
    }

    public function eliminar(UnitTrayId $id): void
    {
        unset($this->store[(string) $id]);
    }
}
