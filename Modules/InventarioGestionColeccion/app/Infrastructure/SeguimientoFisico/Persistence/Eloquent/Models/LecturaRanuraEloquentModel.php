<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LecturaRanuraEloquentModel extends Model
{
    protected $table = 'iot.lecturas_ranura';

    public $timestamps = false;

    protected $fillable = [
        'sincronizacion_id',
        'numero_ranura',
        'rfid_detectado',
        'es_incongruente',
    ];

    protected $casts = [
        'es_incongruente' => 'boolean',
    ];

    public function sincronizacion(): BelongsTo
    {
        return $this->belongsTo(SincronizacionEsp32EloquentModel::class, 'sincronizacion_id');
    }
}
