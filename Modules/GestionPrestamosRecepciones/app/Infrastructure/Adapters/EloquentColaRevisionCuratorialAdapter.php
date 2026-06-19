<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Adapters;

use Modules\GestionPrestamosRecepciones\Application\Ports\ColaRevisionCuratorialPort;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrioridadSolicitud;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;

/**
 * Implementación Eloquent del read-model de la cola de revisión curatorial.
 *
 * Calcula la posición de una solicitud entre las que están pendientes de revisión,
 * ordenando primero las prioritarias y luego por antigüedad.
 */
final class EloquentColaRevisionCuratorialAdapter implements ColaRevisionCuratorialPort
{
    public function posicionEnLaCola(string $solicitudId): int
    {
        $idsOrdenados = SolicitudDepositoEloquentModel::query()
            ->where('estado', EstadoSolicitudDeposito::PendienteDeRevisionPorCuraduria->value)
            ->orderByRaw('CASE WHEN prioridad = ? THEN 0 ELSE 1 END', [PrioridadSolicitud::Prioritaria->value])
            ->orderBy('created_at')
            ->pluck('id')
            ->all();

        $posicion = array_search($solicitudId, $idsOrdenados, true);

        return $posicion === false ? 0 : $posicion + 1;
    }
}
