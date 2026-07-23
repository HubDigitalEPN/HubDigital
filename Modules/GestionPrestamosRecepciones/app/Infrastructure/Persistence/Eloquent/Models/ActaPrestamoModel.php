<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo Eloquent para la tabla 'prestamos.actas_prestamo'.
 *
 * Representa la persistencia del acta formal de un préstamo.
 */
final class ActaPrestamoModel extends Model
{
    protected $table = 'prestamos.actas_prestamo';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'codigo',
        'solicitud_prestamo_id',
        'estado',
        'tipo_prestamo',
        'alcance_prestamo',
        'fecha_inicio',
        'fecha_fin',
        'pdf_ruta',
        'condiciones_generales',
        'pdf_firmado_ruta',
        'documento_identidad_ruta',
        'documento_exportacion_ruta',
        'motivo_devolucion',
        'firmada_subida_en',
        'validada_en',
        'validada_por',
        'pdf_firmado_curador_ruta',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'firmada_subida_en' => 'datetime',
        'validada_en' => 'datetime',
    ];

    /**
     * Relación con la solicitud de préstamo que originó esta acta.
     */
    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudPrestamoModel::class, 'solicitud_prestamo_id');
    }
}
