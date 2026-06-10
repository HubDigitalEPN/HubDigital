<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo Eloquent para la tabla 'recepciones.solicitudes_deposito'.
 *
 * Representa la persistencia de una solicitud de depósito de especímenes.
 */
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
        'nro_individuos',
        'nro_morfoespecies',
        'nro_lotes',
        'extraccion_estado',
        'documentos_procesados',
        'firmas_electronicas',
        'paso_actual',
        'documentos_cargados',
        'nombres_archivos_originales',
        'documentos_requeridos',
        'matriz_id',
    ];

    protected $casts = [
        'sin_documentacion' => 'boolean',
        'documentos_adjuntos' => 'array',
        'datos_faltantes' => 'array',
        'datos_ingresados_manualmente' => 'array',
        'documentos_procesados' => 'array',
        'firmas_electronicas' => 'array',
        'nro_individuos' => 'integer',
        'nro_morfoespecies' => 'integer',
        'nro_lotes' => 'integer',
        'paso_actual' => 'integer',
        'documentos_cargados' => 'array',
        'nombres_archivos_originales' => 'array',
        'documentos_requeridos' => 'array',
    ];
}
