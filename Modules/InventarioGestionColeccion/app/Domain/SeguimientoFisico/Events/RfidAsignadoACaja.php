<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Events;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CodigoRfid;

/**
 * Evento de dominio que registra la vinculación de un tag RFID/NFC con una caja
 * entomológica. A partir de esta asociación, las lecturas posteriores del ESP32 que
 * detecten ese UID pueden resolverse a la caja correspondiente y a su familia asignada.
 */
final readonly class RfidAsignadoACaja
{
    public function __construct(
        public CajaId $cajaId,
        public CodigoRfid $codigoRfid,
        public \DateTimeImmutable $ocurridoEn,
    ) {}
}
