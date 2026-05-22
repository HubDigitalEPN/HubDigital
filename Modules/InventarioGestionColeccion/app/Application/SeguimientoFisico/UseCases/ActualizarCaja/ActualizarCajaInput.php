<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarCaja;

final readonly class ActualizarCajaInput
{
    public function __construct(
        public string $cajaId,
        public bool $esEspecial = false,
        public ?string $observacion = null,
        public ?string $nombre = null,
        public ?int $capacidadMaxima = null,
    ) {}
}
