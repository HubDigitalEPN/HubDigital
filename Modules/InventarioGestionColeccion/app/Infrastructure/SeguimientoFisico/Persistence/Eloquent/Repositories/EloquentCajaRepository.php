<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Caja;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\CajaRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CodigoCaja;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CodigoRfid;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoCaja;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\RanuraId;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models\CajaEloquentModel;

class EloquentCajaRepository implements CajaRepository
{
    public function nextIdentity(): CajaId
    {
        return CajaId::generar();
    }

    public function guardar(Caja $caja): void
    {
        CajaEloquentModel::updateOrCreate(
            ['id' => (string) $caja->id()],
            [
                'codigo' => (string) $caja->codigo(),
                'familia_taxonomica_id' => $caja->familiaTaxonomicaId(),
                'nombre' => $caja->nombre(),
                'capacidad_maxima' => $caja->capacidadMaxima(),
                'estado' => $caja->estadoActual()->valor(),
                'ranura_actual_id' => $caja->ranuraActualId() ? (string) $caja->ranuraActualId() : null,
                'codigo_rfid' => $caja->codigoRfid() ? (string) $caja->codigoRfid() : null,
            ]
        );
    }

    public function buscarPorId(CajaId $id): ?Caja
    {
        $model = CajaEloquentModel::find((string) $id);

        return $model ? $this->toDomain($model) : null;
    }

    public function buscarPorCodigo(CodigoCaja $codigo): ?Caja
    {
        $model = CajaEloquentModel::where('codigo', (string) $codigo)->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function buscarPorCodigoRfid(CodigoRfid $rfid): ?Caja
    {
        $model = CajaEloquentModel::where('codigo_rfid', (string) $rfid)->first();

        return $model ? $this->toDomain($model) : null;
    }

    /** @return Caja[] */
    public function buscarTodas(): array
    {
        return CajaEloquentModel::all()
            ->map(fn ($m) => $this->toDomain($m))
            ->all();
    }

    public function eliminar(CajaId $id): void
    {
        CajaEloquentModel::destroy((string) $id);
    }

    private function toDomain(CajaEloquentModel $model): Caja
    {
        return Caja::reconstituir(
            id: CajaId::desde($model->id),
            codigo: CodigoCaja::desde($model->codigo),
            familiaTaxonomicaId: $model->familia_taxonomica_id,
            estado: EstadoCaja::from($model->estado),
            ranuraActualId: $model->ranura_actual_id ? RanuraId::desde($model->ranura_actual_id) : null,
            codigoRfid: $model->codigo_rfid ? CodigoRfid::desde($model->codigo_rfid) : null,
            nombre: $model->nombre,
            capacidadMaxima: $model->capacidad_maxima,
        );
    }
}
