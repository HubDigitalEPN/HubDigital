<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\RanuraGabinete;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\GabineteId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\RanuraId;

interface RanuraGabineteRepository
{
    public function nextIdentity(): RanuraId;

    public function guardar(RanuraGabinete $ranura): void;

    public function buscarPorId(RanuraId $id): ?RanuraGabinete;

    /** @return RanuraGabinete[] */
    public function buscarPorGabinete(GabineteId $gabineteId): array;

    public function buscarPorNumeroEnGabinete(GabineteId $gabineteId, int $numeroRanura): ?RanuraGabinete;
}
