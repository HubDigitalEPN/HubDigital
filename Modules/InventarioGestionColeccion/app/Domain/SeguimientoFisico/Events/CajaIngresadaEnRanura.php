<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Events;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\RanuraId;

/**
 * Evento de dominio que señala que una caja fue insertada en una ranura concreta,
 * según lo reportado por el barrido del ESP32. Sirve de base para actualizar la
 * ubicación vigente de la caja y para evaluar reglas de seguimiento físico
 * (orden taxonómico, congruencia de familia) sobre la ranura recién ocupada.
 */
final readonly class CajaIngresadaEnRanura
{
    public function __construct(
        public CajaId $cajaId,
        public RanuraId $ranuraId,
        public \DateTimeImmutable $ocurridoEn,
    ) {}
}
