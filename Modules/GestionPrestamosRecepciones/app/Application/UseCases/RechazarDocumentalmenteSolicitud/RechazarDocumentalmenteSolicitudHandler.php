<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\RechazarDocumentalmenteSolicitud;

use Modules\GestionPrestamosRecepciones\Application\Exceptions\SolicitudNoEncontradaException;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\NotificacionCuratoriaPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\NotificacionInvestigadorPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudDepositoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoRechazoDocumental;

/**
 * Manejador del caso de uso para que el curador rechace documentalmente una solicitud
 * indicando el motivo. El tipo de rechazo determina el estado resultante.
 *
 * {@see RechazarDocumentalmenteSolicitudInput}
 * {@see RechazarDocumentalmenteSolicitudOutput}
 */
final class RechazarDocumentalmenteSolicitudHandler
{
    public function __construct(
        private SolicitudDepositoRepositoryInterface $repo,
        private TransactionManagerPort $transactionManager,
        private EventPublisherPort $eventPublisher,
        private NotificacionInvestigadorPort $notificacionInvestigador,
        private NotificacionCuratoriaPort $notificacionCuratoria,
    ) {}

    /**
     * @throws SolicitudNoEncontradaException Si la solicitud no existe.
     */
    public function __invoke(RechazarDocumentalmenteSolicitudInput $input): RechazarDocumentalmenteSolicitudOutput
    {
        $id = SolicitudDepositoId::from($input->solicitudId);
        $solicitud = $this->repo->buscarPorId($id);

        if ($solicitud === null) {
            throw SolicitudNoEncontradaException::conId($input->solicitudId);
        }

        $tipo = TipoRechazoDocumental::from($input->tipoRechazo);

        $solicitud->rechazarDocumentalmente($input->curadorId, $tipo, $input->motivo);

        $this->transactionManager->executeTransactional(function () use ($solicitud): void {
            $this->repo->guardar($solicitud);
            foreach ($solicitud->pullEvents() as $event) {
                $this->eventPublisher->publish($event);
            }
        });

        $notifRef = $this->notificacionInvestigador->notificarRechazoSolicitud(
            solicitudId: (string) $solicitud->id(),
            investigadorId: $solicitud->investigadorId(),
            comentario: $input->motivo,
        );

        $this->notificacionCuratoria->notificarDecisionDocumentalAOtrosCuradores(
            solicitudId: (string) $solicitud->id(),
            curadorQueDecideId: $input->curadorId,
            decision: 'rechazada',
            motivo: $input->motivo,
        );

        return RechazarDocumentalmenteSolicitudOutput::fromPrimitives(
            estado: $solicitud->estado()->value,
            comentarioInvestigador: $solicitud->comentarioCurador(),
            notificacionInvestigadorEnviada: $notifRef !== '',
        );
    }
}
