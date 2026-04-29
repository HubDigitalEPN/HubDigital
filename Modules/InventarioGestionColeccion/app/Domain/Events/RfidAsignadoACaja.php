<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\Events;

use Modules\InventarioGestionColeccion\Domain\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\ValueObjects\CodigoRfid;

final readonly class RfidAsignadoACaja
{
    public function __construct(
        public CajaId $cajaId,
        public CodigoRfid $codigoRfid,
        public \DateTimeImmutable $ocurridoEn,
    ) {}
}
