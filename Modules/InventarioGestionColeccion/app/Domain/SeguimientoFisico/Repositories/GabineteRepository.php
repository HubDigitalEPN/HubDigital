<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Gabinete;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\GabineteId;

interface GabineteRepository
{
    public function nextIdentity(): GabineteId;

    public function guardar(Gabinete $gabinete): void;

    public function buscarPorId(GabineteId $id): ?Gabinete;

    /** @return Gabinete[] */
    public function buscarActivos(): array;
}
