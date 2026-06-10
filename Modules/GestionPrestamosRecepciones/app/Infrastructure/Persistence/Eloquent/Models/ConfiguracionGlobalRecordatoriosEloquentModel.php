<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo Eloquent para la tabla 'prestamos.configuracion_global_recordatorios'.
 *
 * Almacena la cadencia de días configurada por el curador para el envío de recordatorios automáticos.
 */
final class ConfiguracionGlobalRecordatoriosEloquentModel extends Model
{
    protected $table = 'prestamos.configuracion_global_recordatorios';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'curador_id',
        'dias_antes',
    ];

    protected $casts = [
        'dias_antes' => 'array',
    ];
}
