<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo Eloquent para la tabla 'prestamos.verificaciones_entrega_prestamo'.
 *
 * Registra la inspección física realizada por el investigador al recibir los especímenes.
 */
final class VerificacionEntregaPrestamoEloquentModel extends Model
{
    protected $table = 'prestamos.verificaciones_entrega_prestamo';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'prestamo_id',
        'estado_envio',
        'observaciones',
    ];

    protected $casts = [
        'observaciones' => 'array',
    ];

    /**
     * Relación con el préstamo que se está verificando.
     */
    public function prestamo(): BelongsTo
    {
        return $this->belongsTo(PrestamoEloquentModel::class, 'prestamo_id');
    }
}
