<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\AceptarRecepcionConObservaciones;

use Modules\GestionPrestamosRecepciones\Application\Exceptions\RecepcionLoteNoEncontradaException;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\NotificacionInvestigadorPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\RecepcionLoteRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudDepositoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoObservacionRecepcion;

/**
 * Acepta la recepción de un lote con observaciones cuando la anomalía no puede
 * devolverse al investigador: lo deja Verificado con Observaciones, registra las
 * observaciones en el Acta Digital de Recepción, ingresa los especímenes a la
 * colección (en cuarentena si corresponde) y notifica al investigador.
 */
final class AceptarRecepcionConObservacionesHandler
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
    public function __invoke(AceptarRecepcionConObservacionesInput $input): AceptarRecepcionConObservacionesOutput
    {
        $solicitudId = SolicitudDepositoId::from($input->solicitudId);
        $lote = $this->recepcionRepo->buscarPorSolicitudId($solicitudId);

        if ($lote === null) {
            throw RecepcionLoteNoEncontradaException::conSolicitud($input->solicitudId);
        }

        $tipo = TipoObservacionRecepcion::from($input->tipoObservacion);
        $lote->aceptarConObservaciones($tipo, $input->detalleObservacion);

        $solicitud = $this->solicitudRepo->buscarPorId($solicitudId);

        $this->transactionManager->executeTransactional(function () use ($lote): void {
            $this->recepcionRepo->guardar($lote);
            foreach ($lote->pullEvents() as $event) {
                $this->eventPublisher->publish($event);
            }
        });

        $notifRef = $this->notificacionInvestigador->notificarRecepcionConObservaciones(
            solicitudId: (string) $lote->solicitudId(),
            investigadorId: $solicitud?->investigadorId() ?? '',
            observaciones: $lote->observaciones(),
        );

        return AceptarRecepcionConObservacionesOutput::fromPrimitives(
            estado: $lote->estado()->value,
            observacionesRegistradasEnActa: $lote->actaEmitida() && $lote->observaciones() !== [],
            estadoColeccion: $lote->estadoColeccion()->value,
            notificacionInvestigadorEnviada: $notifRef !== '',
        );
    }
}
