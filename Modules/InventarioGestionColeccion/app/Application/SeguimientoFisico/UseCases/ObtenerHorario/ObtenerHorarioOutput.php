<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ObtenerHorario;

/**
 * DTO de salida con el estado del horario hábil único de la colección.
 */
final readonly class ObtenerHorarioOutput
{
    public function __construct(
        public string $id,
        public int $horaInicio,
        public int $horaFin,
        public bool $activo,
    ) {}
}
