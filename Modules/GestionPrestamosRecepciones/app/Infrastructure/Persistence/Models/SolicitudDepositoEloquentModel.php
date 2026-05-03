<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class SolicitudDepositoEloquentModel extends Model
{
    protected $table = 'recepciones.solicitudes_deposito';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'numero',
        'investigador_id',
        'tipo_tramite',
        'estado',
        'origen_recoleccion',
        'situacion_regulatoria',
        'provincia_origen',
        'sin_documentacion',
        'nro_permiso_recoleccion',
        'nro_permiso_movilizacion',
        'grupo_animal',
        'localidad',
        'origen_donacion',
        'documentos_adjuntos',
        'datos_faltantes',
    ];

    protected $casts = [
        'sin_documentacion' => 'boolean',
        'documentos_adjuntos' => 'array',
        'datos_faltantes' => 'array',
    ];
}
