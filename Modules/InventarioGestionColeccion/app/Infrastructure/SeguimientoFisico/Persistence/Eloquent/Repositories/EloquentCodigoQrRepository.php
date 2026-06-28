<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\CodigoQr;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\CodigoQrRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CodigoQrId;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models\CodigoQrEloquentModel;

class EloquentCodigoQrRepository implements CodigoQrRepositoryInterface
{
    public function nextIdentity(): CodigoQrId
    {
        return CodigoQrId::generar();
    }

    public function guardar(CodigoQr $codigoQr): void
    {
        CodigoQrEloquentModel::updateOrCreate(
            ['id' => (string) $codigoQr->id()],
            [
                'especimen_id' => $codigoQr->especimenId(),
                'payload' => $codigoQr->payload(),
            ]
        );
    }

    public function buscarPorEspecimen(string $especimenId): ?CodigoQr
    {
        $model = CodigoQrEloquentModel::where('especimen_id', $especimenId)->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function buscarPorPayload(string $payload): ?CodigoQr
    {
        $model = CodigoQrEloquentModel::where('payload', $payload)->first();

        return $model ? $this->toDomain($model) : null;
    }

    private function toDomain(CodigoQrEloquentModel $model): CodigoQr
    {
        return CodigoQr::reconstituir(
            id: CodigoQrId::desde($model->id),
            especimenId: $model->especimen_id,
            payload: $model->payload,
            generadoEn: $model->created_at?->toDateTimeImmutable() ?? new \DateTimeImmutable,
        );
    }
}
