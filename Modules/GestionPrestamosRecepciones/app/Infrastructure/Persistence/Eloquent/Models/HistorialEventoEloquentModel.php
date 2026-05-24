<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

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
