<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Adapters;

use Modules\GestionPrestamosRecepciones\Application\Ports\EventoHistorialDto;
use Modules\GestionPrestamosRecepciones\Application\Ports\HistorialPort;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudPrestamoId;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\HistorialEventoEloquentModel;

/**
 * Adaptador para el acceso al historial de eventos utilizando Eloquent.
 *
 * Implementa {@see HistorialPort} para recuperar los eventos registrados en el historial
 * filtrados por el tipo de agregado e identificador.
 */
final class EloquentHistorialAdapter implements HistorialPort
{
    /**
     * Obtiene todos los eventos asociados a una solicitud de préstamo.
     *
     * @return list<EventoHistorialDto>
     */
    public function obtenerEventosDeSolicitud(SolicitudPrestamoId $id): array
    {
        return HistorialEventoEloquentModel::where('tipo_agregado', 'solicitud_prestamo')
            ->where('agregado_id', (string) $id)
            ->orderBy('ocurrido_en', 'asc')
            ->get()
            ->map(fn (HistorialEventoEloquentModel $m) => new EventoHistorialDto(
                tipo: $m->tipo_evento,
                ocurridoEn: $m->ocurrido_en,
                datos: $m->datos ?? [],
            ))
            ->all();
    }

    /**
     * Obtiene todos los eventos asociados a un préstamo.
     *
     * @return list<EventoHistorialDto>
     */
    public function obtenerEventosDePrestamo(PrestamoId $id): array
    {
        return HistorialEventoEloquentModel::where('tipo_agregado', 'prestamo')
            ->where('agregado_id', (string) $id)
            ->orderBy('ocurrido_en', 'asc')
            ->get()
            ->map(fn (HistorialEventoEloquentModel $m) => new EventoHistorialDto(
                tipo: $m->tipo_evento,
                ocurridoEn: $m->ocurrido_en,
                datos: $m->datos ?? [],
            ))
            ->all();
    }
}
