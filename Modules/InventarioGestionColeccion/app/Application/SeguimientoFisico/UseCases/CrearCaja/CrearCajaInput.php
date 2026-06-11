<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\CrearCaja;

/**
 * DTO de entrada con los datos para dar de alta una nueva caja (código, RFID y opcionales).
 */
final readonly class CrearCajaInput
{
    public function __construct(
        public string $codigo,
        public string $codigoRfid,
        public bool $esEspecial = false,
        public ?string $observacion = null,
        public ?string $nombre = null,
    ) {}
}
