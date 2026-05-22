<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\Ports;

use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudPrestamoId;

interface HistorialPort
{
    /** @return EventoHistorialDto[] ordenados por ocurridoEn ASC */
    public function obtenerEventosDeSolicitud(SolicitudPrestamoId $id): array;

    /** @return EventoHistorialDto[] ordenados por ocurridoEn ASC */
    public function obtenerEventosDePrestamo(PrestamoId $id): array;
}
