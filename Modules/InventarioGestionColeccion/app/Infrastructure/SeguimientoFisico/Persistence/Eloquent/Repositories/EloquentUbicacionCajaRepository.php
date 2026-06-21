<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\UbicacionCaja;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UbicacionCajaRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\RanuraId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\UbicacionCajaId;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models\UbicacionCajaEloquentModel;

/**
 * Implementación Eloquent del repositorio de ubicaciones de caja: traduce entre la entidad
 * UbicacionCaja y su modelo persistido y resuelve las consultas del historial (la estadía
 * activa de una caja y la última retirada), base para localizar cajas y medir extracciones.
 */
class EloquentUbicacionCajaRepository implements UbicacionCajaRepository
{
    /** Genera un nuevo identificador de ubicación antes de persistirla. */
    public function nextIdentity(): UbicacionCajaId
    {
        return UbicacionCajaId::generar();
    }

    /** Inserta o actualiza la ubicación según su id, registrando ingreso y, si aplica, retiro. */
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

    /** Devuelve la estadía vigente de una caja (sin fecha de retiro), o null si no está ubicada. */
    public function buscarActivaPorCaja(CajaId $cajaId): ?UbicacionCaja
    {
        $model = UbicacionCajaEloquentModel::where('caja_id', (string) $cajaId)
            ->whereNull('retirada_en')
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    /** Devuelve la última estadía ya retirada de una caja, usada para calcular el tiempo de extracción. */
    public function buscarUltimaRetiradaPorCaja(CajaId $cajaId): ?UbicacionCaja
    {
        $model = UbicacionCajaEloquentModel::where('caja_id', (string) $cajaId)
            ->whereNotNull('retirada_en')
            ->orderByDesc('retirada_en')
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    /** Reconstituye la entidad UbicacionCaja a partir de la fila persistida. */
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
