<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SincronizacionEsp32EloquentModel extends Model
{
    protected $table = 'iot.sincronizaciones_esp32';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'gabinete_id',
        'estado',
        'realizada_en',
        'total_incongruencias',
    ];

    public function gabinete(): BelongsTo
    {
        return $this->belongsTo(GabineteEloquentModel::class, 'gabinete_id');
    }

    public function lecturas(): HasMany
    {
        return $this->hasMany(LecturaRanuraEloquentModel::class, 'sincronizacion_id');
    }
}
