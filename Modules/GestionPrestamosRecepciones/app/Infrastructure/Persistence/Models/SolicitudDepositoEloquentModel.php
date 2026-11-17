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
        'documento_ingreso_ruta', 'documento_ingreso_sha256', 'recepcion_qr_en', 'recepcion_qr_por',
        'recepcion_qr_rechazada_en', 'recepcion_qr_motivo', 'contenido_validado_en', 'contenido_validado_por',
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
        'solicitud_nombre_permiso',
        'solicitud_cedula',
        'solicitud_tipo_identificacion',
        'solicitud_texto',
        'solicitud_cargo',
        'solicitud_grupo',
        'solicitud_proyecto',
        'solicitud_institucion',
        'solicitud_correo',
        'solicitud_oficio',
    ];

    protected $casts = [
        'recepcion_qr_en' => 'datetime', 'recepcion_qr_rechazada_en' => 'datetime', 'contenido_validado_en' => 'datetime',
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

    /** La etapa física se muestra sin alterar el estado documental ni el flujo del acta. */
    public function estadoPresentacion(): string
    {
        if ($this->estado !== 'Aprobada Documentalmente') return $this->estado;
        if ($this->contenido_validado_en) return 'Recibida y validada';
        if ($this->recepcion_qr_en) return 'Recibida · pendiente de validar contenido';
        return $this->estado;
    }

    public function alertas(): HasMany
    {
        return $this->hasMany(AlertaSolicitudEloquentModel::class, 'solicitud_deposito_id');
    }
}
