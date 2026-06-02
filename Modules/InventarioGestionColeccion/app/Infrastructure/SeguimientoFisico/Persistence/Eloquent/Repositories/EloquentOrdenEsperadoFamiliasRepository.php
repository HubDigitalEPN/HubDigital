<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\OrdenEsperadoFamiliasRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\OrdenEsperadoFamilias;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models\OrdenEsperadoFamiliasEloquentModel;

class EloquentOrdenEsperadoFamiliasRepository implements OrdenEsperadoFamiliasRepository
{
    /**
     * La secuencia esperada es única para toda la colección; se persiste en una sola fila.
     */
    private const SINGLETON_ID = 'singleton';

    public function obtener(): OrdenEsperadoFamilias
    {
        $model = OrdenEsperadoFamiliasEloquentModel::find(self::SINGLETON_ID);

        if (! $model) {
            return OrdenEsperadoFamilias::vacio();
        }

        return OrdenEsperadoFamilias::desde($model->familias ?? []);
    }

    public function guardar(OrdenEsperadoFamilias $orden): void
    {
        OrdenEsperadoFamiliasEloquentModel::updateOrCreate(
            ['id' => self::SINGLETON_ID],
            ['familias' => $orden->familias()],
        );
    }
}
