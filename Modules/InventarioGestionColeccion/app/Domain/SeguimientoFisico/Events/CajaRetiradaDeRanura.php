<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Events;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;

final readonly class CajaRetiradaDeRanura
{
    public function __construct(
        public CajaId $cajaId,
        public \DateTimeImmutable $ocurridoEn,
    ) {}
}
