<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\Ports;

use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudPrestamoId;

/**
 * Puerto de la capa de aplicación para consultar el historial de eventos ocurridos
 * sobre una solicitud o un préstamo.
 *
 * Lo implementa un adaptador en Infrastructure que lee la tabla de historial de eventos.
 */
interface HistorialPort
{
    /** @return EventoHistorialDto[] ordenados por ocurridoEn ASC */
    public function obtenerEventosDeSolicitud(SolicitudPrestamoId $id): array;

    /** @return EventoHistorialDto[] ordenados por ocurridoEn ASC */
    public function obtenerEventosDePrestamo(PrestamoId $id): array;
}
