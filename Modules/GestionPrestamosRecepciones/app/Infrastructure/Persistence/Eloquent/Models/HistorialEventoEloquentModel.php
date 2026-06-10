<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo Eloquent para la tabla 'prestamos.historial_eventos'.
 *
 * Registra todos los eventos de dominio ocurridos en el módulo para auditoría y trazabilidad.
 */
final class HistorialEventoEloquentModel extends Model
{
    protected $table = 'prestamos.historial_eventos';

    protected $primaryKey = 'id';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tipo_agregado',
        'agregado_id',
        'tipo_evento',
        'datos',
        'ocurrido_en',
    ];

    protected $casts = [
        'datos' => 'array',
        'ocurrido_en' => 'immutable_datetime',
    ];
}
