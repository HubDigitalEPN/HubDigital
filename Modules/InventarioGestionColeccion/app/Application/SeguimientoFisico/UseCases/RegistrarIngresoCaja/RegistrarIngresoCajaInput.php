<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarIngresoCaja;

final readonly class RegistrarIngresoCajaInput
{
    public function __construct(
        public string $cajaId,
        public string $ranuraId,
        public bool $fueraDeHorario,
        public string $actorRol,
    ) {}
}
