<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\SincronizacionEsp32;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\SincronizacionEsp32Repository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CodigoRfid;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\GabineteId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\LecturaRanura;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\SincronizacionEsp32Id;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models\LecturaRanuraEloquentModel;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models\SincronizacionEsp32EloquentModel;

class EloquentSincronizacionEsp32Repository implements SincronizacionEsp32Repository
{
    public function nextIdentity(): SincronizacionEsp32Id
    {
        return SincronizacionEsp32Id::generar();
    }

    public function guardar(SincronizacionEsp32 $sincronizacion): void
    {
        $model = SincronizacionEsp32EloquentModel::updateOrCreate(
            ['id' => (string) $sincronizacion->id()],
            [
                'gabinete_id' => (string) $sincronizacion->gabineteId(),
                'estado' => $sincronizacion->estado()->valor(),
                'realizada_en' => $sincronizacion->realizadaEn()->format('Y-m-d H:i:s'),
                'total_incongruencias' => $sincronizacion->totalIncongruencias(),
            ]
        );

        $model->lecturas()->delete();

        foreach ($sincronizacion->lecturas() as $lectura) {
            $model->lecturas()->create([
                'numero_ranura' => $lectura->numeroRanura(),
                'rfid_detectado' => $lectura->rfidDetectado() ? (string) $lectura->rfidDetectado() : null,
                'es_incongruente' => $lectura->esIncongruente(),
            ]);
        }
    }

    public function buscarUltimaPorGabinete(GabineteId $gabineteId): ?SincronizacionEsp32
    {
        $model = SincronizacionEsp32EloquentModel::with('lecturas')
            ->where('gabinete_id', (string) $gabineteId)
            ->latest('realizada_en')
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    private function toDomain(SincronizacionEsp32EloquentModel $model): SincronizacionEsp32
    {
        $lecturas = $model->lecturas
            ->map(fn (LecturaRanuraEloquentModel $l) => LecturaRanura::crear(
                numeroRanura: $l->numero_ranura,
                rfidDetectado: $l->rfid_detectado ? CodigoRfid::desde($l->rfid_detectado) : null,
                esIncongruente: $l->es_incongruente,
            ))
            ->all();

        return SincronizacionEsp32::registrar(
            id: SincronizacionEsp32Id::desde($model->id),
            gabineteId: GabineteId::desde($model->gabinete_id),
            lecturas: $lecturas,
            realizadaEn: new \DateTimeImmutable($model->realizada_en),
        );
    }
}
