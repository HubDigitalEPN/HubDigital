<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ObtenerHorario;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\HorarioRepository;

final class ObtenerHorarioHandler
{
    public function __construct(
        private readonly HorarioRepository $horarioRepository,
    ) {}

    public function handle(): ObtenerHorarioOutput
    {
        $horario = $this->horarioRepository->obtenerUnico();

        return new ObtenerHorarioOutput(
            id: (string) $horario->id(),
            horaInicio: $horario->horaInicio(),
            horaFin: $horario->horaFin(),
            activo: $horario->activo(),
        );
    }
}
