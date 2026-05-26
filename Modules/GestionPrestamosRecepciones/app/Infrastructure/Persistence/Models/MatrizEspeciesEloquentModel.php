<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function registros(): HasMany
    {
        return $this->hasMany(RegistroEspecimenEloquentModel::class, 'matriz_id');
    }
}
