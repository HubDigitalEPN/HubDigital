<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarSolicitudPrestamo;

use Modules\GestionPrestamosRecepciones\Domain\Exceptions\SolicitudPrestamoNoEncontradaException;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudPrestamoId;

/**
 * Manejador del caso de uso para consultar una solicitud de préstamo.
 * 
 * {@see ConsultarSolicitudPrestamoInput}
 * {@see ConsultarSolicitudPrestamoOutput}
 */
final class ConsultarSolicitudPrestamoHandler
{
    /**
     * @param SolicitudPrestamoRepositoryInterface $repo Repositorio de solicitudes de préstamo.
     */
    public function __construct(
        private readonly SolicitudPrestamoRepositoryInterface $repo,
    ) {}

    /**
     * Ejecuta el caso de uso.
     *
     * @param ConsultarSolicitudPrestamoInput $input Datos de entrada.
     * @return ConsultarSolicitudPrestamoOutput Datos de la solicitud de préstamo.
     * @throws SolicitudPrestamoNoEncontradaException Si la solicitud no existe.
     */
    public function handle(ConsultarSolicitudPrestamoInput $input): ConsultarSolicitudPrestamoOutput
    {
        $id = SolicitudPrestamoId::fromString($input->solicitudId);

        $solicitud = $this->repo->buscarPorId($id);

        if ($solicitud === null) {
            throw SolicitudPrestamoNoEncontradaException::conId($id);
        }

        return ConsultarSolicitudPrestamoOutput::fromEntity($solicitud);
    }
}
