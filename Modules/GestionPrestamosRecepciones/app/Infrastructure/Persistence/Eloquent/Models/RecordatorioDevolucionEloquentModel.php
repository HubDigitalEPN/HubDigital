<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class RecordatorioDevolucionEloquentModel extends Model
{
    protected $table = 'prestamos.recordatorios_devolucion';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'prestamo_id',
        'dias_antes_vencimiento',
        'fecha_programada',
    ];

    protected $casts = [
        'fecha_programada' => 'datetime',
    ];

    public function prestamo(): BelongsTo
    {
        return $this->belongsTo(PrestamoEloquentModel::class, 'prestamo_id');
    }
}
