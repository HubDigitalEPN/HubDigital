<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\PriorizarSolicitudEnCola;

use Modules\GestionPrestamosRecepciones\Application\Exceptions\SolicitudNoEncontradaException;
use Modules\GestionPrestamosRecepciones\Application\Ports\ColaRevisionCuratorialPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudDepositoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrioridadSolicitud;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;

/**
 * Manejador del caso de uso para que el curador clasifique la prioridad de una
 * solicitud en la cola de revisión curatorial.
 *
 * {@see PriorizarSolicitudEnColaInput}
 * {@see PriorizarSolicitudEnColaOutput}
 */
final class PriorizarSolicitudEnColaHandler
{
    public function __construct(
        private SolicitudDepositoRepositoryInterface $repo,
        private TransactionManagerPort $transactionManager,
        private EventPublisherPort $eventPublisher,
        private ColaRevisionCuratorialPort $colaRevision,
    ) {}

    /**
     * @throws SolicitudNoEncontradaException Si la solicitud no existe.
     */
    public function __invoke(PriorizarSolicitudEnColaInput $input): PriorizarSolicitudEnColaOutput
    {
        $id = SolicitudDepositoId::from($input->solicitudId);
        $solicitud = $this->repo->buscarPorId($id);

        if ($solicitud === null) {
            throw SolicitudNoEncontradaException::conId($input->solicitudId);
        }

        $prioridad = PrioridadSolicitud::from($input->prioridad);

        $solicitud->priorizar($prioridad);

        $this->transactionManager->executeTransactional(function () use ($solicitud): void {
            $this->repo->guardar($solicitud);
            foreach ($solicitud->pullEvents() as $event) {
                $this->eventPublisher->publish($event);
            }
        });

        $posicion = $this->colaRevision->posicionEnLaCola((string) $solicitud->id());

        return PriorizarSolicitudEnColaOutput::fromPrimitives(
            prioridad: $prioridad->value,
            posicionEnCola: $posicion,
        );
    }
}
