<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialSolicitud;

use Modules\GestionPrestamosRecepciones\Application\Ports\HistorialPort;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\SolicitudPrestamoNoEncontradaException;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudPrestamoId;

/**
 * Manejador del caso de uso para consultar el historial de una solicitud de préstamo.
 * 
 * {@see ConsultarHistorialSolicitudInput}
 * {@see ConsultarHistorialSolicitudOutput}
 */
final class ConsultarHistorialSolicitudHandler
{
    /**
     * @param SolicitudPrestamoRepositoryInterface $repo Repositorio de solicitudes de préstamo.
     * @param HistorialPort $historial Puerto para acceder al historial.
     */
    public function __construct(
        private readonly SolicitudPrestamoRepositoryInterface $repo,
        private readonly HistorialPort $historial,
    ) {}

    /**
     * Ejecuta el caso de uso.
     *
     * @param ConsultarHistorialSolicitudInput $input Datos de entrada.
     * @return ConsultarHistorialSolicitudOutput Datos del historial de la solicitud.
     * @throws SolicitudPrestamoNoEncontradaException Si la solicitud no existe.
     */
    public function handle(ConsultarHistorialSolicitudInput $input): ConsultarHistorialSolicitudOutput
    {
        $id = SolicitudPrestamoId::fromString($input->solicitudId);

        $solicitud = $this->repo->buscarPorId($id);

        if ($solicitud === null) {
            throw SolicitudPrestamoNoEncontradaException::conId($id);
        }

        $eventos = $this->historial->obtenerEventosDeSolicitud($id);

        return ConsultarHistorialSolicitudOutput::fromEventos((string) $id, $eventos);
    }
}
