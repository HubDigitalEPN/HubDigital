<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\OrdenEsperadoFamiliasRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\OrdenEsperadoFamilias;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models\OrdenEsperadoFamiliasEloquentModel;

/**
 * Implementación Eloquent del repositorio del orden esperado de familias: persiste la
 * secuencia única de la colección en una sola fila (singleton) y la devuelve, entregando una
 * secuencia vacía cuando aún no se ha configurado.
 */
class EloquentOrdenEsperadoFamiliasRepository implements OrdenEsperadoFamiliasRepository
{
    /**
     * La secuencia esperada es única para toda la colección; se persiste en una sola fila.
     */
    private const SINGLETON_ID = 'singleton';

    /** Devuelve la secuencia esperada persistida, o una secuencia vacía si todavía no existe. */
    public function obtener(): OrdenEsperadoFamilias
    {
        $model = OrdenEsperadoFamiliasEloquentModel::find(self::SINGLETON_ID);

        if (! $model) {
            return OrdenEsperadoFamilias::vacio();
        }

        return OrdenEsperadoFamilias::desde($model->familias ?? []);
    }

    /** Persiste la secuencia esperada sobre la fila singleton, creándola si no existía. */
    public function guardar(OrdenEsperadoFamilias $orden): void
    {
        OrdenEsperadoFamiliasEloquentModel::updateOrCreate(
            ['id' => self::SINGLETON_ID],
            ['familias' => $orden->familias()],
        );
    }
}
