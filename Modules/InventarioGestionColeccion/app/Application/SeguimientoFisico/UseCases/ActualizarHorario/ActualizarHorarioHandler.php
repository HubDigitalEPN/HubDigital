<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarHorario;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\HorarioRepository;

final class ActualizarHorarioHandler
{
    public function __construct(
        private readonly HorarioRepository $horarioRepository,
    ) {}

    public function handle(ActualizarHorarioInput $input): ActualizarHorarioOutput
    {
        $horario = $this->horarioRepository->obtenerUnico();

        $horario->actualizar(
            horaInicio: $input->horaInicio,
            horaFin: $input->horaFin,
        );

        $this->horarioRepository->guardar($horario);

        return new ActualizarHorarioOutput(
            id: (string) $horario->id(),
            horaInicio: $horario->horaInicio(),
            horaFin: $horario->horaFin(),
            activo: $horario->activo(),
        );
    }
}
