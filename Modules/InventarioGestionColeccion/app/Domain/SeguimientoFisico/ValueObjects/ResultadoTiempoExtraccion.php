<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects;

/**
 * Resultado de evaluar el tiempo que una caja lleva fuera de su posición, calculado por
 * {@see EvaluadorTiempoExtraccion}. Distingue tres tramos: dentro del límite (sin acción),
 * próxima al límite (notificación preventiva) y excedida (alerta de extracción prolongada).
 */
enum ResultadoTiempoExtraccion
{
    case DentroDelLimite;
    case ProximaAlLimite;
    case Excedida;
}
