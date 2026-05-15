<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarHorario;

final readonly class ActualizarHorarioOutput
{
    public function __construct(
        public string $id,
        public int $horaInicio,
        public int $horaFin,
        public bool $activo,
    ) {}
}
