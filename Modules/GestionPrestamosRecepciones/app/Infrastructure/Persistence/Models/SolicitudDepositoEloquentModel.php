<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'canton_origen',
        'localidad_origen_codigo',
        'validacion_previa_documentos',
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
        'extraccion_metadatos',
        'documentos_procesados',
        'firmas_electronicas',
        'validacion_archivos',
        'paso_actual',
        'documentos_cargados',
        'nombres_archivos_originales',
        'documentos_requeridos',
        'matriz_id',
        'curador_responsable',
        'aprobada_en',
        'rechazada_en',
        'codigo_qr',
        'entrega_programada_para',
        'entrega_notificada_en',
        'entrega_nota',
        'comentario_curador',
        'prioridad',
        'acta_transferencia_dominio',
        'solicitud_firmada_ruta',
        'solicitud_firmada_sha256',
        'solicitud_firmada_en',
        'solicitud_firma_metadata',
        'solicitud_documento_version',
    ];

    protected $casts = [
        'sin_documentacion' => 'boolean',
        'documentos_adjuntos' => 'array',
        'datos_faltantes' => 'array',
        'datos_ingresados_manualmente' => 'array',
        'documentos_procesados' => 'array',
        'extraccion_metadatos' => 'array',
        'firmas_electronicas' => 'array',
        'validacion_archivos' => 'array',
        'validacion_previa_documentos' => 'array',
        'nro_individuos' => 'integer',
        'nro_morfoespecies' => 'integer',
        'nro_lotes' => 'integer',
        'paso_actual' => 'integer',
        'documentos_cargados' => 'array',
        'nombres_archivos_originales' => 'array',
        'documentos_requeridos' => 'array',
        'aprobada_en' => 'datetime',
        'entrega_programada_para' => 'datetime',
        'entrega_notificada_en' => 'datetime',
        'rechazada_en' => 'datetime',
        'acta_transferencia_dominio' => 'array',
        'solicitud_firmada_en' => 'datetime',
        'solicitud_firma_metadata' => 'array',
        'solicitud_documento_version' => 'integer',
    ];

    public function alertas(): HasMany
    {
        return $this->hasMany(AlertaSolicitudEloquentModel::class, 'solicitud_deposito_id');
    }
}
