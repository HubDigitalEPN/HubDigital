<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo Eloquent para la tabla 'prestamos.verificaciones_especimenes'.
 *
 * Registra las verificaciones físicas de especímenes de un préstamo en sus distintos
 * momentos (recepción por el investigador, devolución verificada por el curador).
 */
final class VerificacionEspecimenesEloquentModel extends Model
{
    protected $table = 'prestamos.verificaciones_especimenes';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'prestamo_id',
        'tipo',
        'resultado',
        'observaciones',
        'observacion_general',
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
