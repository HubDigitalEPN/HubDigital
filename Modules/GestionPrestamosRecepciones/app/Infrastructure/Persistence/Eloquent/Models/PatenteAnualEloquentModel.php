<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo Eloquent para la tabla 'prestamos.patentes_anuales'.
 *
 * Almacena la patente del laboratorio ante el MAATE, una por año.
 */
final class PatenteAnualEloquentModel extends Model
{
    protected $table = 'prestamos.patentes_anuales';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'anio',
        'codigo',
        'curador_id',
    ];

    protected $casts = [
        'anio' => 'integer',
    ];
}
