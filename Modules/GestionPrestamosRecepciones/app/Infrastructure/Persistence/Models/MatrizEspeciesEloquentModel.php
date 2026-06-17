<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo Eloquent para la tabla 'recepciones.matrices_especies'.
 *
 * Almacena el encabezado y estado de una matriz de especies (DwC) cargada mediante Excel.
 */
final class MatrizEspeciesEloquentModel extends Model
{
    protected $table = 'recepciones.matrices_especies';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'solicitud_id',
        'tipo_tramite',
        'estado',
        'campos_dwc_presentes',
        'identificacion_original_conservada',
    ];

    protected $casts = [
        'campos_dwc_presentes' => 'array',
        'identificacion_original_conservada' => 'boolean',
    ];

    /**
     * Relación con los registros de especímenes individuales que componen la matriz.
     */
    public function registros(): HasMany
    {
        return $this->hasMany(RegistroEspecimenEloquentModel::class, 'matriz_id');
    }
}
