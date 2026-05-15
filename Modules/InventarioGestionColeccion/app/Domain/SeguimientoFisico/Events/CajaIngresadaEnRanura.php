<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Events;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\RanuraId;

final readonly class CajaIngresadaEnRanura
{
    public function __construct(
        public CajaId $cajaId,
        public RanuraId $ranuraId,
        public \DateTimeImmutable $ocurridoEn,
    ) {}
}
