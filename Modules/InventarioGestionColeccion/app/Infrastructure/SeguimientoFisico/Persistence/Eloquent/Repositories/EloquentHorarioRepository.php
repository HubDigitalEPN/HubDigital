<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Horario;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\HorarioRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\HorarioId;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models\HorarioEloquentModel;

/**
 * Implementación Eloquent del repositorio del horario laboral: gestiona el horario único del
 * componente, creando uno por defecto (8 a 18) la primera vez si todavía no existe.
 */
class EloquentHorarioRepository implements HorarioRepository
{
    /**
     * Devuelve el horario único configurado; si aún no hay ninguno, crea y persiste uno por
     * defecto (jornada de 8 a 18) para que el componente siempre disponga de un horario válido.
     */
    public function obtenerUnico(): Horario
    {
        $model = HorarioEloquentModel::first();

        if (! $model) {
            // Crear y guardar un horario por defecto si no existe
            $defaultHorario = Horario::crear(
                id: HorarioId::generar(),
                horaInicio: 8,
                horaFin: 18,
            );
            $this->guardar($defaultHorario);

            return $defaultHorario;
        }

        return $this->toDomain($model);
    }

    /** Inserta o actualiza el horario según su id. */
    public function guardar(Horario $horario): void
    {
        HorarioEloquentModel::updateOrCreate(
            ['id' => (string) $horario->id()],
            [
                'hora_inicio' => $horario->horaInicio(),
                'hora_fin' => $horario->horaFin(),
                'activo' => $horario->activo(),
            ]
        );
    }

    /** Reconstituye la entidad Horario a partir de la fila persistida. */
    private function toDomain(HorarioEloquentModel $model): Horario
    {
        return Horario::reconstituir(
            id: HorarioId::desde($model->id),
            horaInicio: (int) $model->hora_inicio,
            horaFin: (int) $model->hora_fin,
            activo: (bool) $model->activo,
        );
    }
}
