<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ProcesarEventoEsp32;

/**
 * DTO de entrada con el evento crudo del ESP32: tag RFID, gabinete, índice de slot y tipo de evento.
 */
final readonly class ProcesarEventoEsp32Input
{
    public function __construct(
        public string $tagUid,
        public string $gabineteId,
        public int $slotIndex,
        public string $evento,
    ) {}
}
