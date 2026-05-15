<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarHorario;

final readonly class ActualizarHorarioInput
{
    public function __construct(
        public int $horaInicio,
        public int $horaFin,
    ) {}
}
