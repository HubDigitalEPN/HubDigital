<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class RegistroEspecimenEloquentModel extends Model
{
    protected $table = 'recepciones.registros_especimen';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'matriz_id',
        'nombre_cientifico',
        'nombre_corregido',
        'estado',
        'no_catalogado',
        'motivo_justificacion',
        'datos_dwc',
        'normalizaciones',
    ];

    protected $casts = [
        'no_catalogado' => 'boolean',
        'datos_dwc' => 'array',
        'normalizaciones' => 'array',
    ];

    public function matriz(): BelongsTo
    {
        return $this->belongsTo(MatrizEspeciesEloquentModel::class, 'matriz_id');
    }
}
