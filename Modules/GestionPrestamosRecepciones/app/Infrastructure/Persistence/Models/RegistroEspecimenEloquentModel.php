<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo Eloquent para la tabla 'recepciones.registros_especimen'.
 *
 * Almacena los datos de un espécimen individual dentro de una matriz DwC.
 */
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
        'comentario_justificacion',
        'datos_dwc',
        'normalizaciones',
        'correcciones_curatoriales',
        // Espécimen del inventario que produjo esta fila (referencia opaca)
        'especimen_id',
    ];

    protected $casts = [
        'no_catalogado' => 'boolean',
        'datos_dwc' => 'array',
        'normalizaciones' => 'array',
        'correcciones_curatoriales' => 'array',
    ];

    /**
     * Relación con la matriz de especies a la que pertenece este registro.
     */
    public function matriz(): BelongsTo
    {
        return $this->belongsTo(MatrizEspeciesEloquentModel::class, 'matriz_id');
    }
}
