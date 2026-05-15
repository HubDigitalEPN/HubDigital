<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Notificacion;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\NotificacionRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\NotificacionId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TipoNotificacion;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models\NotificacionEloquentModel;

class EloquentNotificacionRepository implements NotificacionRepository
{
    public function nextIdentity(): NotificacionId
    {
        return NotificacionId::generar();
    }

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

    public function buscarUltimaNotificacionPorCaja(CajaId $cajaId): ?Notificacion
    {
        $model = NotificacionEloquentModel::where('caja_id', (string) $cajaId)
            ->latest()
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

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
