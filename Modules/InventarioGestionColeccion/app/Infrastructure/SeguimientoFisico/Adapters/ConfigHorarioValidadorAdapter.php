<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Adapters;

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\HorarioValidadorPort;

/**
 * Adaptador de respaldo que resuelve el horario laboral con una jornada fija codificada
 * (8:00 a 18:00), sin consultar la base de datos. Sirve como implementación por defecto
 * del {@see HorarioValidadorPort} cuando aún no existe un horario configurado.
 */
class ConfigHorarioValidadorAdapter implements HorarioValidadorPort
{
    /**
     * Indica si la fecha cae fuera de la jornada fija (antes de las 8 o desde las 18 en
     * adelante), usado para decidir si un evento del ESP32 ocurre en horario no laboral.
     */
    public function esFueraDeHorario(\DateTimeImmutable $fecha): bool
    {
        $hora = (int) $fecha->format('H');

        // Fixed dummy logic for business hours (8 to 18)
        return $hora < 8 || $hora >= 18;
    }
}
