<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarDonacionConTransferencia;

use Modules\GestionPrestamosRecepciones\Application\Exceptions\SolicitudNoEncontradaException;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\NotificacionInvestigadorPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudDepositoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;

/**
 * Manejador del caso de uso para que el curador apruebe documentalmente una donación,
 * generando además el Acta de Transferencia de Dominio.
 *
 * {@see AprobarDonacionConTransferenciaInput}
 * {@see AprobarDonacionConTransferenciaOutput}
 */
final class AprobarDonacionConTransferenciaHandler
{
    public function __construct(
        private SolicitudDepositoRepositoryInterface $repo,
        private TransactionManagerPort $transactionManager,
        private EventPublisherPort $eventPublisher,
        private NotificacionInvestigadorPort $notificacionInvestigador,
    ) {}

    /**
     * @throws SolicitudNoEncontradaException Si la solicitud no existe.
     */
    public function __invoke(AprobarDonacionConTransferenciaInput $input): AprobarDonacionConTransferenciaOutput
    {
        $id = SolicitudDepositoId::from($input->solicitudId);
        $solicitud = $this->repo->buscarPorId($id);

        if ($solicitud === null) {
            throw SolicitudNoEncontradaException::conId($input->solicitudId);
        }

        // El dominio genera el Acta de Transferencia de Dominio cuando el trámite es Donación.
        $solicitud->aprobarDocumentalmente($input->curadorId);

        $this->transactionManager->executeTransactional(function () use ($solicitud): void {
            $this->repo->guardar($solicitud);
            foreach ($solicitud->pullEvents() as $event) {
                $this->eventPublisher->publish($event);
            }
        });

        $codigoQR = (string) $solicitud->codigoQR();

        $notifRef = $this->notificacionInvestigador->notificarCodigoQrDisponible(
            solicitudId: (string) $solicitud->id(),
            investigadorId: $solicitud->investigadorId(),
            codigoQR: $codigoQR,
        );

        return AprobarDonacionConTransferenciaOutput::fromPrimitives(
            estado: $solicitud->estado()->value,
            curadorResponsable: $solicitud->curadorResponsable(),
            codigoQR: $codigoQR,
            codigoQRDisponible: $codigoQR !== '',
            actaTransferenciaDominioRuta: $solicitud->actaTransferenciaDominio()?->ruta() ?? '',
            notificacionInvestigadorEnviada: $notifRef !== '',
        );
    }
}
