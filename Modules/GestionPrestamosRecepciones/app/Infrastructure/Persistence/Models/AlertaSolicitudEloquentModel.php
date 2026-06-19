<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo Eloquent para la tabla 'recepciones.alertas_solicitud'.
 *
 * Representa la persistencia de una alerta justificada asociada a una solicitud de
 * depósito. Modelo anémico: sin lógica de negocio.
 */
final class AlertaSolicitudEloquentModel extends Model
{
    protected $table = 'recepciones.alertas_solicitud';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'solicitud_deposito_id',
        'tipo',
        'justificacion_investigador',
        'estado_revision',
    ];

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudDepositoEloquentModel::class, 'solicitud_deposito_id');
    }
}
