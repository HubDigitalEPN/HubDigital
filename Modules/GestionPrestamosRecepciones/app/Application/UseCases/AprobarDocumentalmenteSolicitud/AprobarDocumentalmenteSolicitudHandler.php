<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarDocumentalmenteSolicitud;

use Modules\GestionPrestamosRecepciones\Application\Exceptions\SolicitudNoEncontradaException;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\NotificacionCuratoriaPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\NotificacionInvestigadorPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\SolicitudDepositoYaProcesada;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\MatrizEspeciesRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudDepositoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;

/**
 * Manejador del caso de uso para que el curador apruebe documentalmente una solicitud
 * que no presenta alertas pendientes.
 *
 * {@see AprobarDocumentalmenteSolicitudInput}
 * {@see AprobarDocumentalmenteSolicitudOutput}
 */
final class AprobarDocumentalmenteSolicitudHandler
{
    public function __construct(
        private SolicitudDepositoRepositoryInterface $repo,
        private TransactionManagerPort $transactionManager,
        private EventPublisherPort $eventPublisher,
        private NotificacionInvestigadorPort $notificacionInvestigador,
        private MatrizEspeciesRepositoryInterface $matrizRepo,
        private NotificacionCuratoriaPort $notificacionCuratoria,
    ) {}

    /**
     * @throws SolicitudNoEncontradaException Si la solicitud no existe.
     */
    public function __invoke(AprobarDocumentalmenteSolicitudInput $input): AprobarDocumentalmenteSolicitudOutput
    {
        $id = SolicitudDepositoId::from($input->solicitudId);
        $solicitud = null;
        $notifRef = '';
        $this->transactionManager->executeTransactional(function () use ($id, $input, &$solicitud, &$notifRef): void {
            $solicitud = $this->repo->buscarPorIdParaActualizar($id);
            if ($solicitud === null) {
                throw SolicitudNoEncontradaException::conId($input->solicitudId);
            }

            if ($solicitud->estado() === EstadoSolicitudDeposito::AprobadaDocumentalmente) {
                throw SolicitudDepositoYaProcesada::aprobada();
            }

            $solicitud->aprobarDocumentalmente($input->curadorId);
            $this->repo->guardar($solicitud);
            $notifRef = $this->notificacionInvestigador->notificarCodigoQrDisponible(
                (string) $solicitud->id(), $solicitud->investigadorId(), (string) $solicitud->codigoQR(),
            );
            foreach ($solicitud->pullEvents() as $event) {
                $this->eventPublisher->publish($event);
            }
        });

        $codigoQR = (string) $solicitud->codigoQR();

        $this->notificacionCuratoria->notificarDecisionDocumentalAOtrosCuradores(
            solicitudId: (string) $solicitud->id(),
            curadorQueDecideId: $input->curadorId,
            decision: 'aprobada',
        );

        // Aviso agrupado: si curaduría sanó celdas de la matriz, el depositante se
        // entera aquí en un solo mensaje y no en uno por celda tocada.
        $matriz = $this->matrizRepo->buscarPorSolicitudId((string) $solicitud->id());

        if ($matriz !== null && $matriz->correccionesCuratoriales() !== []) {
            $this->notificacionInvestigador->notificarCorreccionesCuratoriales(
                solicitudId: (string) $solicitud->id(),
                investigadorId: $solicitud->investigadorId(),
                correcciones: $matriz->correccionesCuratoriales(),
            );
        }

        return AprobarDocumentalmenteSolicitudOutput::fromPrimitives(
            estado: $solicitud->estado()->value,
            curadorResponsable: $solicitud->curadorResponsable(),
            codigoQR: $codigoQR,
            codigoQRDisponible: $codigoQR !== '',
            notificacionInvestigadorEnviada: false,
            notificacionInvestigadorProgramada: $notifRef !== '',
        );
    }
}
