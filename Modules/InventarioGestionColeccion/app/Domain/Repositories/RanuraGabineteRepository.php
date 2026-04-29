<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\Repositories;

use Modules\InventarioGestionColeccion\Domain\Entities\RanuraGabinete;
use Modules\InventarioGestionColeccion\Domain\ValueObjects\GabineteId;
use Modules\InventarioGestionColeccion\Domain\ValueObjects\RanuraId;

interface RanuraGabineteRepository
{
    public function nextIdentity(): RanuraId;

    public function guardar(RanuraGabinete $ranura): void;

    public function buscarPorId(RanuraId $id): ?RanuraGabinete;

    /** @return RanuraGabinete[] */
    public function buscarPorGabinete(GabineteId $gabineteId): array;

    public function buscarPorNumeroEnGabinete(GabineteId $gabineteId, int $numeroRanura): ?RanuraGabinete;
}
