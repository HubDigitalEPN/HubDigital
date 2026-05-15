<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\EventoCicloIot;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EventoCicloIotRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\ActorRol;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models\EventoCicloIotEloquentModel;

class EloquentEventoCicloIotRepository implements EventoCicloIotRepository
{
    public function guardar(EventoCicloIot $evento): void
    {
        EventoCicloIotEloquentModel::create([
            'tipo_agregado' => $evento->tipoAgregado(),
            'agregado_id' => $evento->agregadoId(),
            'tipo_evento' => $evento->tipoEvento(),
            'version_evento' => $evento->versionEvento(),
            'datos' => $evento->datos(),
            'actor_id' => $evento->actorId(),
            'actor_rol' => $evento->actorRol()->valor(),
            'ocurrido_en' => $evento->ocurridoEn()->format('Y-m-d H:i:s'),
        ]);
    }

    public function buscarUltimoPorAgregadoYTipo(string $agregadoId, string $tipoEvento): ?EventoCicloIot
    {
        $model = EventoCicloIotEloquentModel::where('agregado_id', $agregadoId)
            ->where('tipo_evento', $tipoEvento)
            ->orderByDesc('ocurrido_en')
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    private function toDomain(EventoCicloIotEloquentModel $model): EventoCicloIot
    {
        return EventoCicloIot::registrar(
            tipoAgregado: $model->tipo_agregado,
            agregadoId: $model->agregado_id,
            tipoEvento: $model->tipo_evento,
            versionEvento: $model->version_evento,
            datos: $model->datos ?? [],
            actorId: $model->actor_id,
            actorRol: ActorRol::from($model->actor_rol),
            ocurridoEn: new \DateTimeImmutable($model->ocurrido_en),
        );
    }
}
