<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarCaja;

/**
 * DTO de entrada con los campos editables de una caja para el caso de uso de actualización.
 */
final readonly class ActualizarCajaInput
{
    public function __construct(
        public string $cajaId,
        public bool $esEspecial = false,
        public ?string $observacion = null,
        public ?string $nombre = null,
    ) {}
}
