<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports;

interface HorarioValidadorPort
{
    public function estaFueraDeHorario(\DateTimeImmutable $fecha): bool;
}
