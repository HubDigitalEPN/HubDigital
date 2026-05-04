<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Adapters;

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\HorarioValidadorPort;

class ConfigHorarioValidadorAdapter implements HorarioValidadorPort
{
    public function estaFueraDeHorario(\DateTimeImmutable $fecha): bool
    {
        $hora = (int) $fecha->format('H');

        // Fixed dummy logic for business hours (8 to 18)
        return $hora < 8 || $hora >= 18;
    }
}
