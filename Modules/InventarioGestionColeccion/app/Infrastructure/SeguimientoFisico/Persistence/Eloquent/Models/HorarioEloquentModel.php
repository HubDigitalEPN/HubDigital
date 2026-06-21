<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo Eloquent que mapea la tabla del horario laboral del componente (hora de inicio y
 * fin de la jornada), usado para decidir si un evento ocurre fuera de horario. Es el puente
 * de persistencia de la entidad Horario del dominio.
 */
class HorarioEloquentModel extends Model
{
    protected $table = 'iot.horarios';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'hora_inicio',
        'hora_fin',
        'activo',
    ];

    protected $casts = [
        'id' => 'string',
        'hora_inicio' => 'integer',
        'hora_fin' => 'integer',
        'activo' => 'boolean',
    ];
}
