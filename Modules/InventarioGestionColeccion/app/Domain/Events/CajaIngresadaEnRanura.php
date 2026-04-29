<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\Events;

use Modules\InventarioGestionColeccion\Domain\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\ValueObjects\RanuraId;

final readonly class CajaIngresadaEnRanura
{
    public function __construct(
        public CajaId $cajaId,
        public RanuraId $ranuraId,
        public \DateTimeImmutable $ocurridoEn,
    ) {}
}
