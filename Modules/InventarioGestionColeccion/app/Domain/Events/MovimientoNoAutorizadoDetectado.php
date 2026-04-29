<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\Events;

use Modules\InventarioGestionColeccion\Domain\ValueObjects\CajaId;

final readonly class MovimientoNoAutorizadoDetectado
{
    public function __construct(
        public CajaId $cajaId,
        public \DateTimeImmutable $ocurridoEn,
    ) {}
}
