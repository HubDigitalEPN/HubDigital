<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\AceptarRecepcionConObservaciones;

use Modules\GestionPrestamosRecepciones\Application\Exceptions\RecepcionLoteNoEncontradaException;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\NotificacionCuratoriaPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\NotificacionInvestigadorPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\RecepcionLoteRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudDepositoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ItemChecklistRecepcion;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;

/**
 * Acepta la recepción de un lote con observaciones cuando la anomalía no puede
 * devolverse al investigador: lo deja Verificado con Observaciones, registra las
 * observaciones que irán al Acta Digital de Recepción, propone cuarentena cuando
 * corresponde y notifica al depositante. El alta espera el acta final firmada.
 */
final class AceptarRecepcionConObservacionesHandler
{
    public function __construct(
        private readonly RecepcionLoteRepositoryInterface $recepcionRepo,
        private readonly SolicitudDepositoRepositoryInterface $solicitudRepo,
        private readonly TransactionManagerPort $transactionManager,
        private readonly EventPublisherPort $eventPublisher,
        private readonly NotificacionInvestigadorPort $notificacionInvestigador,
        private readonly NotificacionCuratoriaPort $notificacionCuratoria,
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

        $solicitud = $this->solicitudRepo->buscarPorId($solicitudId);
        $solicitud?->asegurarContenidoRecepcionValidado();

        $itemsNoConformes = array_map(
            static fn (string $item): ItemChecklistRecepcion => ItemChecklistRecepcion::from($item),
            array_values($input->itemsNoConformes),
        );
        $lote->aceptarConObservaciones($itemsNoConformes, $input->comentario, $input->receptorId);

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
        $this->notificacionCuratoria->notificarLoteRecibidoParaActa(
            solicitudId: (string) $lote->solicitudId(),
            receptorId: $input->receptorId,
            conObservaciones: true,
        );

        return AceptarRecepcionConObservacionesOutput::fromPrimitives(
            estado: $lote->estado()->value,
            observacionesRegistradasEnActa: false,
            estadoColeccion: $lote->estadoColeccion()->value,
            notificacionInvestigadorEnviada: $notifRef !== '',
        );
    }
}
