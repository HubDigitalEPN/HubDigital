<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarHorario;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\HorarioRepository;

/**
 * Caso de uso: actualizar la franja horaria hábil única de la colección (hora de inicio y
 * fin), que el sistema usa para detectar movimientos de cajas fuera de horario.
 *
 * @see ActualizarHorarioInput
 * @see ActualizarHorarioOutput
 */
final class ActualizarHorarioHandler
{
    /**
     * @param  HorarioRepository  $horarioRepository  Recupera el horario único y persiste sus cambios.
     */
    public function __construct(
        private readonly HorarioRepository $horarioRepository,
    ) {}

    /**
     * Carga el horario único, le aplica la nueva hora de inicio y fin, lo persiste y devuelve
     * su estado resultante.
     */
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
