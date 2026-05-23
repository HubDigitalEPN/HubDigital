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
        'nombre_investigador_documento',
        'documentos_adjuntos',
        'datos_faltantes',
        'datos_ingresados_manualmente',
        'extraccion_estado',
        'documentos_procesados',
        'paso_actual',
        'documentos_cargados',
        'nombres_archivos_originales',
        'documentos_requeridos',
    ];

    protected $casts = [
        'sin_documentacion' => 'boolean',
        'documentos_adjuntos' => 'array',
        'datos_faltantes' => 'array',
        'datos_ingresados_manualmente' => 'array',
        'documentos_procesados' => 'array',
        'paso_actual' => 'integer',
        'documentos_cargados' => 'array',
        'nombres_archivos_originales' => 'array',
        'documentos_requeridos' => 'array',
    ];
}
