<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\RanuraGabinete;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\RanuraGabineteRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\GabineteId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\RanuraId;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models\RanuraGabineteEloquentModel;

class EloquentRanuraGabineteRepository implements RanuraGabineteRepository
{
    public function nextIdentity(): RanuraId
    {
        return RanuraId::generar();
    }

    public function guardar(RanuraGabinete $ranura): void
    {
        RanuraGabineteEloquentModel::updateOrCreate(
            ['id' => (string) $ranura->id()],
            [
                'gabinete_id' => (string) $ranura->gabineteId(),
                'numero_ranura' => $ranura->numeroRanura(),
                'familia_taxonomica_esperada_id' => $ranura->familiaTaxonomicaEsperadaId(),
                'caja_actual_id' => $ranura->cajaActualId() ? (string) $ranura->cajaActualId() : null,
                'activa' => $ranura->activa(),
            ]
        );
    }

    public function buscarPorId(RanuraId $id): ?RanuraGabinete
    {
        $model = RanuraGabineteEloquentModel::find((string) $id);

        return $model ? $this->toDomain($model) : null;
    }

    public function buscarPorGabinete(GabineteId $gabineteId): array
    {
        return RanuraGabineteEloquentModel::where('gabinete_id', (string) $gabineteId)
            ->get()
            ->map(fn (RanuraGabineteEloquentModel $m) => $this->toDomain($m))
            ->all();
    }

    public function buscarPorNumeroEnGabinete(GabineteId $gabineteId, int $numeroRanura): ?RanuraGabinete
    {
        $model = RanuraGabineteEloquentModel::where('gabinete_id', (string) $gabineteId)
            ->where('numero_ranura', $numeroRanura)
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    private function toDomain(RanuraGabineteEloquentModel $model): RanuraGabinete
    {
        return RanuraGabinete::reconstituir(
            id: RanuraId::desde($model->id),
            gabineteId: GabineteId::desde($model->gabinete_id),
            numeroRanura: $model->numero_ranura,
            familiaTaxonomicaEsperadaId: $model->familia_taxonomica_esperada_id,
            cajaActualId: $model->caja_actual_id ? CajaId::desde($model->caja_actual_id) : null,
            activa: (bool) $model->activa,
        );
    }
}
