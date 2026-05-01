<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\AlertaUbicacion;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\AlertaUbicacionRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\AlertaUbicacionId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoAlerta;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TipoAlerta;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models\AlertaUbicacionEloquentModel;

class EloquentAlertaUbicacionRepository implements AlertaUbicacionRepository
{
    public function nextIdentity(): AlertaUbicacionId
    {
        return AlertaUbicacionId::generar();
    }

    public function guardar(AlertaUbicacion $alerta): void
    {
        AlertaUbicacionEloquentModel::updateOrCreate(
            ['id' => (string) $alerta->id()],
            [
                'caja_id' => (string) $alerta->cajaId(),
                'tipo' => $alerta->tipo()->valor(),
                'estado' => $alerta->estado()->valor(),
                'datos_contexto' => $alerta->datosContexto(),
            ]
        );
    }

    public function buscarActivaPorCaja(CajaId $cajaId): ?AlertaUbicacion
    {
        $model = AlertaUbicacionEloquentModel::where('caja_id', (string) $cajaId)
            ->where('estado', EstadoAlerta::Activa->valor())
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    private function toDomain(AlertaUbicacionEloquentModel $model): AlertaUbicacion
    {
        return AlertaUbicacion::reconstituir(
            id: AlertaUbicacionId::desde($model->id),
            cajaId: CajaId::desde($model->caja_id),
            tipo: TipoAlerta::from($model->tipo),
            estado: EstadoAlerta::from($model->estado),
            datosContexto: $model->datos_contexto ?? [],
        );
    }
}
