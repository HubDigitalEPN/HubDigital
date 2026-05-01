<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\UbicacionCaja;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UbicacionCajaRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\RanuraId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\UbicacionCajaId;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models\UbicacionCajaEloquentModel;

class EloquentUbicacionCajaRepository implements UbicacionCajaRepository
{
    public function nextIdentity(): UbicacionCajaId
    {
        return UbicacionCajaId::generar();
    }

    public function guardar(UbicacionCaja $ubicacion): void
    {
        UbicacionCajaEloquentModel::updateOrCreate(
            ['id' => (string) $ubicacion->id()],
            [
                'caja_id' => (string) $ubicacion->cajaId(),
                'ranura_gabinete_id' => (string) $ubicacion->ranuraGabineteId(),
                'ingresada_en' => $ubicacion->ingresadaEn()->format('Y-m-d H:i:s'),
                'retirada_en' => $ubicacion->retiradaEn()?->format('Y-m-d H:i:s'),
            ]
        );
    }

    public function buscarActivaPorCaja(CajaId $cajaId): ?UbicacionCaja
    {
        $model = UbicacionCajaEloquentModel::where('caja_id', (string) $cajaId)
            ->whereNull('retirada_en')
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    private function toDomain(UbicacionCajaEloquentModel $model): UbicacionCaja
    {
        return UbicacionCaja::reconstituir(
            id: UbicacionCajaId::desde($model->id),
            cajaId: CajaId::desde($model->caja_id),
            ranuraGabineteId: RanuraId::desde($model->ranura_gabinete_id),
            ingresadaEn: new \DateTimeImmutable($model->ingresada_en),
            retiradaEn: $model->retirada_en ? new \DateTimeImmutable($model->retirada_en) : null,
        );
    }
}
