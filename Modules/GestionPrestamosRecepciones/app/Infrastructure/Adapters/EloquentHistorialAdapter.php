<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Adapters;

use Modules\GestionPrestamosRecepciones\Application\Ports\EventoHistorialDto;
use Modules\GestionPrestamosRecepciones\Application\Ports\HistorialPort;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudPrestamoId;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\HistorialEventoEloquentModel;

final class EloquentHistorialAdapter implements HistorialPort
{
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
