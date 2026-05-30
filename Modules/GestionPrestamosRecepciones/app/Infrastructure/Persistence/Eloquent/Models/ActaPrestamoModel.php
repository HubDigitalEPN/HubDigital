<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ActaPrestamoModel extends Model
{
    protected $table = 'prestamos.actas_prestamo';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'numero_prestamo',
        'solicitud_prestamo_id',
        'estado',
        'tipo_prestamo',
        'fecha_inicio',
        'fecha_fin',
        'pdf_ruta',
        'condiciones_generales',
        'pdf_firmado_ruta',
        'documento_identidad_ruta',
        'motivo_devolucion',
        'firmada_subida_en',
        'validada_en',
        'validada_por',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'firmada_subida_en' => 'datetime',
        'validada_en' => 'datetime',
    ];

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudPrestamoModel::class, 'solicitud_prestamo_id');
    }
}
