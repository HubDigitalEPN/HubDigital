<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Events;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\GabineteId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\RanuraId;

/**
 * Evento de dominio que se emite cuando una caja ingresa en una ranura cuya familia
 * taxonómica esperada aún no ha sido configurada, de modo que no es posible evaluar
 * la congruencia de su contenido. Permite avisar al curador de la configuración
 * faltante en lugar de silenciar la verificación.
 */
final readonly class FamiliaNoAsignadaDetectada
{
    public function __construct(
        public CajaId $cajaId,
        public RanuraId $ranuraId,
        public GabineteId $gabineteId,
        public \DateTimeImmutable $ocurridoEn,
    ) {}
}
