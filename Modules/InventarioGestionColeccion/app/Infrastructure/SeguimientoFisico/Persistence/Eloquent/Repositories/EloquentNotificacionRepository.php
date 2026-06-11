<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Notificacion;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\NotificacionRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\NotificacionId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TipoNotificacion;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models\NotificacionEloquentModel;

/**
 * Implementación Eloquent del repositorio de notificaciones: traduce entre la entidad
 * Notificacion y su modelo persistido y permite consultar la última notificación de una caja
 * (para evitar repetir avisos en periodos cortos).
 */
class EloquentNotificacionRepository implements NotificacionRepository
{
    /** Genera un nuevo identificador de notificación antes de persistirla. */
    public function nextIdentity(): NotificacionId
    {
        return NotificacionId::generar();
    }

    /** Inserta o actualiza la notificación según su id. */
    public function guardar(Notificacion $notificacion): void
    {
        NotificacionEloquentModel::updateOrCreate(
            ['id' => (string) $notificacion->id()],
            [
                'caja_id' => (string) $notificacion->cajaId(),
                'tipo' => $notificacion->tipoNotificacion()->valor(),
                'datos_contexto' => $notificacion->datosContexto(),
            ]
        );
    }

    /** Devuelve la notificación más reciente emitida para una caja, o null si no hay ninguna. */
    public function buscarUltimaNotificacionPorCaja(CajaId $cajaId): ?Notificacion
    {
        $model = NotificacionEloquentModel::where('caja_id', (string) $cajaId)
            ->latest()
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    /** Reconstituye la entidad Notificacion a partir de la fila persistida. */
    private function toDomain(NotificacionEloquentModel $model): Notificacion
    {
        return Notificacion::crear(
            id: NotificacionId::desde($model->id),
            cajaId: CajaId::desde($model->caja_id),
            tipo: TipoNotificacion::from($model->tipo),
            datosContexto: $model->datos_contexto ?? [],
        );
    }
}
