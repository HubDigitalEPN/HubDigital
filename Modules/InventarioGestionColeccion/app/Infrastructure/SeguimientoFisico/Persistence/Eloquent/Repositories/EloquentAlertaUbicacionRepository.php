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

/**
 * Implementación Eloquent del repositorio de alertas de ubicación: traduce entre la entidad
 * de dominio AlertaUbicacion y su modelo persistido, y resuelve las consultas que el
 * componente necesita (alerta activa por caja, listado por estado).
 */
class EloquentAlertaUbicacionRepository implements AlertaUbicacionRepository
{
    /** Genera un nuevo identificador de alerta antes de persistirla. */
    public function nextIdentity(): AlertaUbicacionId
    {
        return AlertaUbicacionId::generar();
    }

    /** Inserta o actualiza la alerta según su id, reflejando su estado y datos de contexto. */
    public function guardar(AlertaUbicacion $alerta): void
    {
        AlertaUbicacionEloquentModel::updateOrCreate(
            ['id' => (string) $alerta->id()],
            [
                'caja_id' => (string) $alerta->cajaId(),
                'tipo' => $alerta->tipo()->valor(),
                'estado' => $alerta->estado()->valor(),
                'datos_contexto' => $alerta->datosContexto(),
                'motivo_resolucion' => $alerta->motivoResolucion(),
            ]
        );
    }

    /** Devuelve la alerta activa de una caja, si existe, para evitar duplicar alertas abiertas. */
    public function buscarActivaPorCaja(CajaId $cajaId): ?AlertaUbicacion
    {
        $model = AlertaUbicacionEloquentModel::where('caja_id', (string) $cajaId)
            ->where('estado', EstadoAlerta::Activa->valor())
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    /** Recupera una alerta por su identificador. */
    public function buscarPorId(AlertaUbicacionId $id): ?AlertaUbicacion
    {
        $model = AlertaUbicacionEloquentModel::find((string) $id);

        return $model ? $this->toDomain($model) : null;
    }

    /**
     * Lista las alertas ordenadas de más reciente a más antigua, filtrando por estado
     * cuando se indica uno.
     *
     * @return AlertaUbicacion[]
     */
    public function buscarTodas(?EstadoAlerta $estado = null): array
    {
        $query = AlertaUbicacionEloquentModel::orderByDesc('created_at');

        if ($estado !== null) {
            $query->where('estado', $estado->valor());
        }

        return $query->get()
            ->map(fn ($m) => $this->toDomain($m))
            ->all();
    }

    /** Reconstituye la entidad de dominio a partir de la fila persistida. */
    private function toDomain(AlertaUbicacionEloquentModel $model): AlertaUbicacion
    {
        return AlertaUbicacion::reconstituir(
            id: AlertaUbicacionId::desde($model->id),
            cajaId: CajaId::desde($model->caja_id),
            tipo: TipoAlerta::from($model->tipo),
            estado: EstadoAlerta::from($model->estado),
            datosContexto: $model->datos_contexto ?? [],
            generadaEn: $model->created_at->toDateTimeImmutable(),
            motivoResolucion: $model->motivo_resolucion,
        );
    }
}
