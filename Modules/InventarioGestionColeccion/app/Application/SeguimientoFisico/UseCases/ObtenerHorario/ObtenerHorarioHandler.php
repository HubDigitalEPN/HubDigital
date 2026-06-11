<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ObtenerHorario;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\HorarioRepository;

/**
 * Caso de uso: obtener la franja horaria hábil única de la colección para mostrarla o editarla.
 *
 * @see ObtenerHorarioOutput
 */
final class ObtenerHorarioHandler
{
    /**
     * @param  HorarioRepository  $horarioRepository  Recupera el horario único.
     */
    public function __construct(
        private readonly HorarioRepository $horarioRepository,
    ) {}

    /**
     * Recupera el horario único y devuelve su estado actual.
     */
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
