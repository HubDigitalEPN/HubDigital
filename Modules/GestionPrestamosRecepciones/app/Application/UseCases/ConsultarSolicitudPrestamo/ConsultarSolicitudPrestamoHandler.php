<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarSolicitudPrestamo;

use Modules\GestionPrestamosRecepciones\Domain\Exceptions\SolicitudPrestamoNoEncontradaException;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudPrestamoId;

final class ConsultarSolicitudPrestamoHandler
{
    public function __construct(
        private readonly SolicitudPrestamoRepositoryInterface $repo,
    ) {}

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
