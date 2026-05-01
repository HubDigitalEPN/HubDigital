<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarRetiroCaja;

final readonly class RegistrarRetiroCajaInput
{
    public function __construct(
        public string $cajaId,
        public bool $fueraDeHorario,
        public string $actorRol,
    ) {}
}
