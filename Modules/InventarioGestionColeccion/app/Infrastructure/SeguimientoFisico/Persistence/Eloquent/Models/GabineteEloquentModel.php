<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo Eloquent que mapea la tabla de gabinetes (armarios metálicos) del componente, con
 * su código, total de ranuras y si está activo. Es el puente de persistencia de la entidad
 * Gabinete del dominio.
 */
class GabineteEloquentModel extends Model
{
    protected $table = 'iot.gabinetes';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'codigo',
        'nombre',
        'total_ranuras',
        'activo',
    ];

    /** Ranuras que componen el gabinete. */
    public function ranuras(): HasMany
    {
        return $this->hasMany(RanuraGabineteEloquentModel::class, 'gabinete_id');
    }

    /** Barridos del ESP32 registrados para el gabinete. */
    public function sincronizaciones(): HasMany
    {
        return $this->hasMany(SincronizacionEsp32EloquentModel::class, 'gabinete_id');
    }
}
