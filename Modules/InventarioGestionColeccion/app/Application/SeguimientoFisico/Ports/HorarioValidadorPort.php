<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports;

/**
 * Determina si un instante cae fuera del horario hábil configurado de la colección.
 * Lo usan los handlers de ingreso/retiro de cajas para marcar movimientos en horario
 * no laboral, que el dominio trata como sospechosos.
 */
interface HorarioValidadorPort
{
    /** Indica si la fecha indicada queda fuera del horario hábil de la colección. */
    public function esFueraDeHorario(\DateTimeImmutable $fecha): bool;
}
