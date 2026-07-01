<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarSolicitudProrroga;

use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudProrrogaRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;

/**
 * Caso de uso de lectura: obtiene la solicitud de prórroga pendiente de un préstamo
 * (fecha propuesta y justificación) para mostrarla al curador. Devuelve null si no
 * hay ninguna pendiente.
 */
final class ConsultarSolicitudProrrogaHandler
{
    public function __construct(
        private readonly SolicitudProrrogaRepositoryInterface $repo,
    ) {}

    public function handle(ConsultarSolicitudProrrogaInput $input): ?ConsultarSolicitudProrrogaOutput
    {
        $solicitud = $this->repo->buscarPendientePorPrestamo(
            PrestamoId::fromString($input->prestamoId)
        );

        if ($solicitud === null) {
            return null;
        }

        return new ConsultarSolicitudProrrogaOutput(
            solicitudProrrogaId: (string) $solicitud->id(),
            prestamoId: (string) $solicitud->prestamoId(),
            fechaPropuesta: $solicitud->fechaPropuesta(),
            justificacion: $solicitud->justificacion(),
            estado: $solicitud->estado()->value,
            solicitadaEn: $solicitud->solicitadaEn(),
        );
    }
}
