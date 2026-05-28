<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UnitTrayEspecimenRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\UnitTrayId;

final class InMemoryUnitTrayEspecimenRepository implements UnitTrayEspecimenRepository
{
    /** @var array<string, string> especimenId => unitTrayId */
    private array $asignaciones = [];

    public function sincronizar(UnitTrayId $unitTrayId, array $especimenIds): void
    {
        $tray = (string) $unitTrayId;

        // Quita las asignaciones actuales de este tray.
        $this->asignaciones = array_filter(
            $this->asignaciones,
            fn (string $trayAsignado) => $trayAsignado !== $tray,
        );

        foreach (array_unique($especimenIds) as $especimenId) {
            $this->asignaciones[$especimenId] = $tray;
        }
    }

    /** @return string[] */
    public function especimenIdsPorUnitTray(UnitTrayId $unitTrayId): array
    {
        $tray = (string) $unitTrayId;

        return array_values(array_keys(array_filter(
            $this->asignaciones,
            fn (string $trayAsignado) => $trayAsignado === $tray,
        )));
    }

    public function unitTrayDeEspecimen(string $especimenId): ?UnitTrayId
    {
        return isset($this->asignaciones[$especimenId])
            ? UnitTrayId::desde($this->asignaciones[$especimenId])
            : null;
    }

    public function eliminarPorUnitTray(UnitTrayId $unitTrayId): void
    {
        $tray = (string) $unitTrayId;

        $this->asignaciones = array_filter(
            $this->asignaciones,
            fn (string $trayAsignado) => $trayAsignado !== $tray,
        );
    }
}
