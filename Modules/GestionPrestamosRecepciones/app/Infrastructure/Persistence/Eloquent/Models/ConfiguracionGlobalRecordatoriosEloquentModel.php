<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class ConfiguracionGlobalRecordatoriosEloquentModel extends Model
{
    protected $table = 'recordatorios.configuracion_global_recordatorios';

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
