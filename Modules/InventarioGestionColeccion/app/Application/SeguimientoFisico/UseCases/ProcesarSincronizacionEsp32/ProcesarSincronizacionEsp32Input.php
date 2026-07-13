<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ProcesarSincronizacionEsp32;

/**
 * DTO de entrada con el snapshot completo de un barrido del ESP32.
 * Cada lectura representa una ranura escaneada: tag_uid nulo = ranura vacía confirmada.
 * Los slots ausentes del array no se tocan (red de seguridad ante barridos parciales).
 *
 * @property-read array<int, array{slotIndex: int, tagUid: ?string}> $lecturas
 */
final readonly class ProcesarSincronizacionEsp32Input
{
    /**
     * @param  string  $gabineteId  UUID del gabinete cuyo barrido se está ingresando.
     * @param  array<int, array{slotIndex: int, tagUid: ?string}>  $lecturas  Resultado del barrido:
     *                                                                        slot_index (base 0) + tag_uid hex de 8 chars o null.
     */
    public function __construct(
        public string $gabineteId,
        public array $lecturas,
    ) {}
}
