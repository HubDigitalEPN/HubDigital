<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

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
}
