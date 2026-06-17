<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\SolicitarIntervencionCuratoria;

use Modules\GestionPrestamosRecepciones\Application\Exceptions\SolicitudNoEncontradaException;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\NotificacionCuratoriaPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudDepositoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;

/**
 * Escala una solicitud a intervención curatoria por problemas en el proceso.
 *
 * {@see SolicitarIntervencionCuratoriaInput}
 * {@see SolicitarIntervencionCuratoriaOutput}
 */
final class SolicitarIntervencionCuratoriaHandler
{
    public function __construct(
        private SolicitudDepositoRepositoryInterface $repo,
        private TransactionManagerPort $transactionManager,
        private EventPublisherPort $eventPublisher,
        private NotificacionCuratoriaPort $notificacionCuratoria,
    ) {}

    /**
     * @param SolicitarIntervencionCuratoriaInput $input
     * @return SolicitarIntervencionCuratoriaOutput
     * @throws SolicitudNoEncontradaException
     */
    public function __invoke(SolicitarIntervencionCuratoriaInput $input): SolicitarIntervencionCuratoriaOutput
    {
        $id = SolicitudDepositoId::from($input->solicitudId);
        $solicitud = $this->repo->buscarPorId($id);

        if ($solicitud === null) {
            throw SolicitudNoEncontradaException::conId($input->solicitudId);
        }

        $solicitud->escalarAIntervencionCuratoria();

        $this->transactionManager->executeTransactional(function () use ($solicitud): void {
            $this->repo->guardar($solicitud);
            foreach ($solicitud->pullEvents() as $event) {
                $this->eventPublisher->publish($event);
            }
        });

        $curadorNotificadoId = $this->notificacionCuratoria->notificarIntervencionRequerida(
            solicitudId: (string) $solicitud->id(),
            investigadorId: $input->investigadorId,
        );

        return new SolicitarIntervencionCuratoriaOutput(
            cargaDocumentalPausada: true,
            notificacionCuradorEnviada: true,
            curadorNotificadoId: $curadorNotificadoId,
        );
    }
}
