<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\RechazarRecepcionLote;

use Modules\GestionPrestamosRecepciones\Application\Exceptions\RecepcionLoteNoEncontradaException;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\NotificacionInvestigadorPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\RecepcionLoteRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudDepositoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\MotivoFalloRecepcion;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;

/**
 * Suspende la recepción de un lote por una anomalía subsanable: lo deja en Recepción
 * Suspendida, emite la orden de acción correctiva y notifica al investigador. El
 * Código QR permanece vigente para reintentar la recepción del mismo lote.
 */
final class RechazarRecepcionLoteHandler
{
    public function __construct(
        private readonly RecepcionLoteRepositoryInterface $recepcionRepo,
        private readonly SolicitudDepositoRepositoryInterface $solicitudRepo,
        private readonly TransactionManagerPort $transactionManager,
        private readonly EventPublisherPort $eventPublisher,
        private readonly NotificacionInvestigadorPort $notificacionInvestigador,
    ) {}

    /**
     * @throws RecepcionLoteNoEncontradaException Si el lote no fue iniciado.
     */
    public function __invoke(RechazarRecepcionLoteInput $input): RechazarRecepcionLoteOutput
    {
        $solicitudId = SolicitudDepositoId::from($input->solicitudId);
        $lote = $this->recepcionRepo->buscarPorSolicitudId($solicitudId);

        if ($lote === null) {
            throw RecepcionLoteNoEncontradaException::conSolicitud($input->solicitudId);
        }

        $motivo = MotivoFalloRecepcion::from($input->motivoFallo);
        $lote->rechazarPorAnomaliaSubsanable($motivo);

        $solicitud = $this->solicitudRepo->buscarPorId($solicitudId);

        $this->transactionManager->executeTransactional(function () use ($lote): void {
            $this->recepcionRepo->guardar($lote);
            foreach ($lote->pullEvents() as $event) {
                $this->eventPublisher->publish($event);
            }
        });

        $notifRef = $this->notificacionInvestigador->notificarOrdenAccionCorrectiva(
            solicitudId: (string) $lote->solicitudId(),
            investigadorId: $solicitud?->investigadorId() ?? '',
            motivoFallo: $lote->motivoFallo()->value,
            accionCorrectiva: $lote->accionCorrectiva()->value,
        );

        return RechazarRecepcionLoteOutput::fromPrimitives(
            estado: $lote->estado()->value,
            ordenAccionCorrectiva: $lote->accionCorrectiva()->value,
            codigoQrVigente: (string) $lote->codigoQR() !== '',
            notificacionInvestigadorEnviada: $notifRef !== '',
        );
    }
}
