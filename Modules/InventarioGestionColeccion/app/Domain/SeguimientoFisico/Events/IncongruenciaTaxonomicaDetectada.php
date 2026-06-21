<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Events;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\GabineteId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\RanuraId;

/**
 * Evento de dominio que materializa la alerta TaxonomicMismatch del seguimiento físico:
 * se emite cuando la familia taxonómica de la caja ingresada no coincide con la familia
 * esperada para la ranura que ocupa. Es la regla de negocio que detecta cajas colocadas
 * en el lugar equivocado dentro del gabinete.
 */
final readonly class IncongruenciaTaxonomicaDetectada
{
    public function __construct(
        public CajaId $cajaId,
        public RanuraId $ranuraId,
        public GabineteId $gabineteId,
        public \DateTimeImmutable $ocurridoEn,
    ) {}
}
