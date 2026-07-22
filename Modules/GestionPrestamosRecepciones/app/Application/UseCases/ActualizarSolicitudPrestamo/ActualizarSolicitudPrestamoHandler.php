<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ActualizarSolicitudPrestamo;

use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Application\Services\ResolverItemsSolicitud;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\SolicitudPrestamoNoEncontradaException;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudPrestamoId;

/**
 * Manejador para actualizar una solicitud de préstamo.
 * 
 * {@see ActualizarSolicitudPrestamoInput}
 * {@see ActualizarSolicitudPrestamoOutput}
 */
final class ActualizarSolicitudPrestamoHandler
{
    public function __construct(
        private readonly SolicitudPrestamoRepositoryInterface $repo,
        private readonly EventPublisherPort                   $publisher,
        private readonly TransactionManagerPort               $transactionManager,
        private readonly ResolverItemsSolicitud               $resolverItems,
    )
    {
    }

    /**
     * Ejecuta la actualización de la solicitud de préstamo.
     * 
     * @param ActualizarSolicitudPrestamoInput $input
     * @return ActualizarSolicitudPrestamoOutput
     * @throws SolicitudPrestamoNoEncontradaException
     */
    public function handle(ActualizarSolicitudPrestamoInput $input): ActualizarSolicitudPrestamoOutput
    {
        $id = SolicitudPrestamoId::fromString($input->solicitudId);
        $solicitud = $this->repo->buscarPorId($id);

        if ($solicitud === null) {
            throw SolicitudPrestamoNoEncontradaException::conId($id);
        }

        // Contrasta contra el catálogo antes de persistir: valida disponibilidad
        // y cantidad, y refresca el snapshot de cada espécimen.
        $items = $this->resolverItems->resolver($input->items);

        $solicitud->actualizar(
            tituloEstudio: $input->tituloEstudio,
            institucionAdscripcion: $input->institucionAdscripcion,
            lineaInvestigacion: $input->lineaInvestigacion,
            propositoPrestamo: $input->propositoPrestamo,
            duracionPropuestaMeses: $input->duracionPropuestaMeses,
            items: $items,
            justificacionExtendida: $input->justificacionExtendida,
        );

        $this->transactionManager->executeTransactional(function () use ($solicitud): void {
            $this->repo->guardar($solicitud);
            foreach ($solicitud->pullEvents() as $event) {
                $this->publisher->publish($event);
            }
        });

        return ActualizarSolicitudPrestamoOutput::fromPrimitives($solicitud);
    }
}
