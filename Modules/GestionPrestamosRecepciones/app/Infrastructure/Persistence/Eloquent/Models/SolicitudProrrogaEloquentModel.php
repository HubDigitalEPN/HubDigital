<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo Eloquent de la solicitud de prórroga de un préstamo.
 *
 * Detalle de persistencia del agregado
 * {@see \Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudProrroga}.
 */
final class SolicitudProrrogaEloquentModel extends Model
{
    protected $table = 'prestamos.solicitudes_prorroga';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'prestamo_id',
        'fecha_propuesta',
        'justificacion',
        'estado',
        'solicitada_en',
        'comentario_resolucion',
        'resuelta_en',
    ];

    protected $casts = [
        'fecha_propuesta' => 'datetime',
        'solicitada_en' => 'datetime',
        'resuelta_en' => 'datetime',
    ];
}
