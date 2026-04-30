<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class SolicitudPrestamoModel extends Model
{
    protected $table = 'solicitudes_prestamo';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'numero_solicitud',
        'investigador_id',
        'estado',
        'titulo_estudio',
        'institucion_adscripcion',
        'linea_investigacion',
        'proposito_prestamo',
        'duracion_propuesta_meses',
        'justificacion_extendida',
        'comentario_curador',
        'enviada_en',
        'resuelta_en',
        'resuelta_por',
    ];

    protected $casts = [
        'enviada_en' => 'datetime',
        'resuelta_en' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(ItemPrestamoModel::class, 'solicitud_prestamo_id');
    }

    public function acta(): HasOne
    {
        return $this->hasOne(ActaPrestamoModel::class, 'solicitud_prestamo_id');
    }
}
