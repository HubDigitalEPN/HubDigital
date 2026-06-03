<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\DatasetConfig;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\DatasetConfigId;

interface DatasetConfigRepositoryInterface
{
    public function nextIdentity(): DatasetConfigId;

    public function guardar(DatasetConfig $datasetConfig): void;

    public function obtenerActual(): ?DatasetConfig;

    public function buscarPorId(DatasetConfigId $id): ?DatasetConfig;
}
