<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo Eloquent para la tabla 'prestamos.prestamos'.
 *
 * Representa la persistencia de un préstamo activo en el sistema.
 */
final class PrestamoEloquentModel extends Model
{
    protected $table = 'prestamos.prestamos';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'acta_prestamo_id',
        'investigador_id',
        'estado',
        'iniciado_en',
        'fecha_fin',
    ];

    protected $casts = [
        'iniciado_en' => 'datetime',
        'fecha_fin' => 'datetime',
    ];

    /**
     * Relación con el acta de préstamo vinculada.
     */
    public function acta(): BelongsTo
    {
        return $this->belongsTo(ActaPrestamoModel::class, 'acta_prestamo_id');
    }
}
