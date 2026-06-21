<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarHorario;

/**
 * DTO de entrada con la hora de inicio y fin (en horas del día) de la franja hábil.
 */
final readonly class ActualizarHorarioInput
{
    public function __construct(
        public int $horaInicio,
        public int $horaFin,
    ) {}
}
