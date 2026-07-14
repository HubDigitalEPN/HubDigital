<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\EventoCicloIot;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EventoCicloIotRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\ActorRol;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models\EventoCicloIotEloquentModel;

/**
 * Implementación Eloquent del repositorio de eventos del ciclo IoT: agrega entradas a la
 * bitácora append-only y recupera el último evento de un agregado por tipo, base para
 * reconstruir el estado más reciente sin recorrer todo el historial.
 */
class EloquentEventoCicloIotRepository implements EventoCicloIotRepository
{
    /** Agrega un nuevo evento a la bitácora (siempre inserta, nunca actualiza). */
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

    /** Devuelve el evento más reciente de un agregado para un tipo dado, o null si no hay ninguno. */
    public function buscarUltimoPorAgregadoYTipo(string $agregadoId, string $tipoEvento): ?EventoCicloIot
    {
        $model = EventoCicloIotEloquentModel::where('agregado_id', $agregadoId)
            ->where('tipo_evento', $tipoEvento)
            ->orderByDesc('ocurrido_en')
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    /**
     * Devuelve todos los eventos de un agregado en orden cronológico ascendente.
     *
     * @return EventoCicloIot[]
     */
    public function buscarPorAgregado(string $tipoAgregado, string $agregadoId): array
    {
        return EventoCicloIotEloquentModel::where('tipo_agregado', $tipoAgregado)
            ->where('agregado_id', $agregadoId)
            ->orderBy('ocurrido_en')
            ->get()
            ->map(fn (EventoCicloIotEloquentModel $m) => $this->toDomain($m))
            ->all();
    }

    /** Reconstituye la entidad de evento a partir de la fila persistida. */
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
