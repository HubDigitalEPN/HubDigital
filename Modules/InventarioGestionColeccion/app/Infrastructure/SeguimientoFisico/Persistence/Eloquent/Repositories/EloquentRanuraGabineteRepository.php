<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\RanuraGabinete;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\RanuraGabineteRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\GabineteId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\RanuraId;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models\RanuraGabineteEloquentModel;

/**
 * Implementación Eloquent del repositorio de ranuras: traduce entre la entidad RanuraGabinete
 * y su modelo persistido y resuelve las consultas del barrido (por número en un gabinete y las
 * ranuras vecinas ocupadas, clave para validar el orden taxonómico entre cajas contiguas).
 */
class EloquentRanuraGabineteRepository implements RanuraGabineteRepository
{
    /** Genera un nuevo identificador de ranura antes de persistirla. */
    public function nextIdentity(): RanuraId
    {
        return RanuraId::generar();
    }

    /** Inserta o actualiza la ranura según su id, incluyendo la caja que aloja actualmente. */
    public function guardar(RanuraGabinete $ranura): void
    {
        RanuraGabineteEloquentModel::updateOrCreate(
            ['id' => (string) $ranura->id()],
            [
                'gabinete_id' => (string) $ranura->gabineteId(),
                'numero_ranura' => $ranura->numeroRanura(),
                'caja_actual_id' => $ranura->cajaActualId() ? (string) $ranura->cajaActualId() : null,
                'activa' => $ranura->activa(),
            ]
        );
    }

    /** Elimina la ranura indicada de la persistencia. */
    public function eliminar(RanuraId $id): void
    {
        RanuraGabineteEloquentModel::where('id', (string) $id)->delete();
    }

    /** Recupera una ranura por su identificador. */
    public function buscarPorId(RanuraId $id): ?RanuraGabinete
    {
        $model = RanuraGabineteEloquentModel::find((string) $id);

        return $model ? $this->toDomain($model) : null;
    }

    /**
     * Lista las ranuras de un gabinete ordenadas por su número.
     *
     * @return RanuraGabinete[]
     */
    public function buscarPorGabinete(GabineteId $gabineteId): array
    {
        return RanuraGabineteEloquentModel::where('gabinete_id', (string) $gabineteId)
            ->orderBy('numero_ranura')
            ->get()
            ->map(fn (RanuraGabineteEloquentModel $m) => $this->toDomain($m))
            ->all();
    }

    /** Busca la ranura con un número concreto dentro de un gabinete (la posición del lector que reporta el ESP32). */
    public function buscarPorNumeroEnGabinete(GabineteId $gabineteId, int $numeroRanura): ?RanuraGabinete
    {
        $model = RanuraGabineteEloquentModel::where('gabinete_id', (string) $gabineteId)
            ->where('numero_ranura', $numeroRanura)
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    /**
     * Devuelve las ranuras ocupadas inmediatamente anterior y siguiente a una posición dada,
     * que son las cajas contiguas contra las que se evalúa el orden taxonómico de la colección.
     *
     * @return RanuraGabinete[]
     */
    public function buscarVecinasOcupadas(GabineteId $gabineteId, int $numeroRanura): array
    {
        $resultado = [];

        $anterior = RanuraGabineteEloquentModel::where('gabinete_id', (string) $gabineteId)
            ->whereNotNull('caja_actual_id')
            ->where('numero_ranura', '<', $numeroRanura)
            ->orderByDesc('numero_ranura')
            ->first();

        if ($anterior !== null) {
            $resultado[] = $this->toDomain($anterior);
        }

        $siguiente = RanuraGabineteEloquentModel::where('gabinete_id', (string) $gabineteId)
            ->whereNotNull('caja_actual_id')
            ->where('numero_ranura', '>', $numeroRanura)
            ->orderBy('numero_ranura')
            ->first();

        if ($siguiente !== null) {
            $resultado[] = $this->toDomain($siguiente);
        }

        return $resultado;
    }

    /** Reconstituye la entidad RanuraGabinete a partir de la fila persistida. */
    private function toDomain(RanuraGabineteEloquentModel $model): RanuraGabinete
    {
        return RanuraGabinete::reconstituir(
            id: RanuraId::desde($model->id),
            gabineteId: GabineteId::desde($model->gabinete_id),
            numeroRanura: $model->numero_ranura,
            cajaActualId: $model->caja_actual_id ? CajaId::desde($model->caja_actual_id) : null,
            activa: (bool) $model->activa,
        );
    }
}
