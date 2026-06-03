<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MuestraColectaEloquentModel extends Model
{
    protected $table = 'taxonomia.muestras_colecta';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'codigo_muestra',
        'fecha_colecta',
        'fecha_verbatim',
        'localidad_id',
        'localidad_verbatim',
        'colector',
        'sampling_protocol',
        'estado_revision',
        'motivo_revision',
    ];

    protected $casts = [
        'fecha_colecta' => 'date',
    ];

    public function localidad(): BelongsTo
    {
        return $this->belongsTo(LocalidadEloquentModel::class, 'localidad_id');
    }
}
