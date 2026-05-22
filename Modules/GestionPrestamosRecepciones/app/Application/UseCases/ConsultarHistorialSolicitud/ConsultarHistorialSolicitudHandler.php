<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialSolicitud;

use Modules\GestionPrestamosRecepciones\Application\Ports\HistorialPort;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\SolicitudPrestamoNoEncontradaException;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudPrestamoId;

final class ConsultarHistorialSolicitudHandler
{
    public function __construct(
        private readonly SolicitudPrestamoRepositoryInterface $repo,
        private readonly HistorialPort $historial,
    ) {}

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
