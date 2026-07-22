<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo Eloquent para la tabla 'prestamos.items_prestamo'.
 *
 * Representa un espécimen o lote individual dentro de una solicitud de préstamo.
 */
final class ItemPrestamoModel extends Model
{
    protected $table = 'prestamos.items_prestamo';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'solicitud_prestamo_id',
        'especimen_codigo_externo',
        'especimen_id',
        'cantidad_solicitada',
        'especimen_snapshot',
        'condiciones_especificas',
    ];

    protected $casts = [
        'cantidad_solicitada' => 'integer',
        'especimen_snapshot'  => 'array',
    ];

    /**
     * Relación con la solicitud de préstamo a la que pertenece este ítem.
     */
    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudPrestamoModel::class, 'solicitud_prestamo_id');
    }
}
