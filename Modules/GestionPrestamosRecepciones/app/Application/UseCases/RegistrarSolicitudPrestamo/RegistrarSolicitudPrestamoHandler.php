<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\RegistrarSolicitudPrestamo;

use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\GeneradorCodigoPrestamo;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Application\Services\ResolverItemsSolicitud;
use Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\AlcancePrestamo;

/**
 * Registra una nueva solicitud de préstamo de especímenes.
 *
 * {@see RegistrarSolicitudPrestamoInput}
 * {@see RegistrarSolicitudPrestamoOutput}
 */
final class RegistrarSolicitudPrestamoHandler
{
    public function __construct(
        private readonly SolicitudPrestamoRepositoryInterface $repo,
        private readonly GeneradorCodigoPrestamo $generadorCodigo,
        private readonly EventPublisherPort $publisher,
        private readonly TransactionManagerPort $transactionManager,
        private readonly ResolverItemsSolicitud $resolverItems,
    ) {}

    /**
     * @param RegistrarSolicitudPrestamoInput $input
     * @return RegistrarSolicitudPrestamoOutput
     */
    public function handle(RegistrarSolicitudPrestamoInput $input): RegistrarSolicitudPrestamoOutput
    {
        $id = $this->repo->nextIdentity();
        $codigoPrestamo = $this->generadorCodigo->siguiente();

        // Contrasta contra el catálogo antes de persistir: valida disponibilidad
        // y cantidad, y congela el snapshot de cada espécimen.
        $items = $this->resolverItems->resolver($input->items);

        $solicitud = SolicitudPrestamo::crear(
            id: $id,
            codigoPrestamo: $codigoPrestamo,
            investigadorId: $input->investigadorId,
            alcancePrestamo: AlcancePrestamo::from($input->alcancePrestamo),
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

        return RegistrarSolicitudPrestamoOutput::fromPrimitives($solicitud);
    }
}
