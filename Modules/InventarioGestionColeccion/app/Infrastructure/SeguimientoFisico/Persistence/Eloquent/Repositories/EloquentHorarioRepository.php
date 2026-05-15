<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Horario;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\HorarioRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\HorarioId;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models\HorarioEloquentModel;

class EloquentHorarioRepository implements HorarioRepository
{
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
