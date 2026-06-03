<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\DatasetConfig;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\DatasetConfigRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\DatasetConfigId;

final class InMemoryDatasetConfigRepository implements DatasetConfigRepositoryInterface
{
    /** @var array<string, DatasetConfig> */
    private array $store = [];

    /** @var string[] orden de inserción para resolver obtenerActual */
    private array $orden = [];

    public function nextIdentity(): DatasetConfigId
    {
        return DatasetConfigId::generar();
    }

    public function guardar(DatasetConfig $datasetConfig): void
    {
        $idStr = (string) $datasetConfig->id();

        if (! isset($this->store[$idStr])) {
            $this->orden[] = $idStr;
        }

        $this->store[$idStr] = $datasetConfig;
    }

    public function obtenerActual(): ?DatasetConfig
    {
        if ($this->orden === []) {
            return null;
        }

        $ultimoId = $this->orden[count($this->orden) - 1];

        return $this->store[$ultimoId] ?? null;
    }

    public function buscarPorId(DatasetConfigId $id): ?DatasetConfig
    {
        return $this->store[(string) $id] ?? null;
    }
}
