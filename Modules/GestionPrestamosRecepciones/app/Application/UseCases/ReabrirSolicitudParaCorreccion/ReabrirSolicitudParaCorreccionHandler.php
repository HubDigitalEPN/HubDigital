<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ReabrirSolicitudParaCorreccion;

use Modules\GestionPrestamosRecepciones\Application\Exceptions\SolicitudNoEncontradaException;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudDepositoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;

/**
 * Manejador del caso de uso para que el depositante reabra una solicitud rechazada
 * de forma subsanable ("Requiere Corrección") y la deje editable ("En Borrador").
 *
 * {@see ReabrirSolicitudParaCorreccionInput}
 */
final class ReabrirSolicitudParaCorreccionHandler
{
    public function __construct(
        private SolicitudDepositoRepositoryInterface $repo,
        private TransactionManagerPort $transactionManager,
        private EventPublisherPort $eventPublisher,
    ) {}

    /**
     * @throws SolicitudNoEncontradaException Si la solicitud no existe.
     * @throws \DomainException Si la solicitud no pertenece al investigador.
     */
    public function __invoke(ReabrirSolicitudParaCorreccionInput $input): void
    {
        $solicitud = $this->repo->buscarPorId(SolicitudDepositoId::from($input->solicitudId));

        if ($solicitud === null) {
            throw SolicitudNoEncontradaException::conId($input->solicitudId);
        }

        if ($solicitud->investigadorId() !== $input->investigadorId) {
            throw new \DomainException('La solicitud no pertenece al investigador.');
        }

        $solicitud->reabrirParaCorreccion();

        $this->transactionManager->executeTransactional(function () use ($solicitud): void {
            $this->repo->guardar($solicitud);
            foreach ($solicitud->pullEvents() as $event) {
                $this->eventPublisher->publish($event);
            }
        });
    }
}
