<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Modelo Eloquent para la tabla 'prestamos.solicitudes_prestamo'.
 *
 * Representa la persistencia de una solicitud de préstamo de especímenes realizada por un investigador.
 */
final class SolicitudPrestamoModel extends Model
{
    protected $table = 'prestamos.solicitudes_prestamo';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'numero_solicitud',
        'investigador_id',
        'alcance_prestamo',
        'estado',
        'titulo_estudio',
        'institucion_adscripcion',
        'linea_investigacion',
        'proposito_prestamo',
        'duracion_propuesta_meses',
        'justificacion_extendida',
        'comentario_curador',
        'enviada_en',
        'resuelta_en',
        'resuelta_por',
    ];

    protected $casts = [
        'enviada_en' => 'datetime',
        'resuelta_en' => 'datetime',
    ];

    /**
     * Relación con el usuario investigador que realiza la solicitud.
     */
    public function investigador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'investigador_id');
    }

    /**
     * Relación con los ítems (especímenes) solicitados.
     */
    public function items(): HasMany
    {
        return $this->hasMany(ItemPrestamoModel::class, 'solicitud_prestamo_id');
    }

    /**
     * Relación con el acta de préstamo generada tras la aprobación.
     */
    public function acta(): HasOne
    {
        return $this->hasOne(ActaPrestamoModel::class, 'solicitud_prestamo_id');
    }
}
