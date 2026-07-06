<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarRecepcionLote;

use Modules\GestionPrestamosRecepciones\Application\Exceptions\RecepcionLoteNoEncontradaException;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\NotificacionInvestigadorPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\RecepcionLoteRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudDepositoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ItemVerificacionRecepcion;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;

/**
 * Aprueba la recepción de un lote que cumple todos los ítems de la lista de
 * verificación: lo deja Verificado Físicamente, ingresa los especímenes a la
 * colección, emite el Acta Digital de Recepción y notifica al investigador.
 */
final class AprobarRecepcionLoteHandler
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
    public function __invoke(AprobarRecepcionLoteInput $input): AprobarRecepcionLoteOutput
    {
        $solicitudId = SolicitudDepositoId::from($input->solicitudId);
        $lote = $this->recepcionRepo->buscarPorSolicitudId($solicitudId);

        if ($lote === null) {
            throw RecepcionLoteNoEncontradaException::conSolicitud($input->solicitudId);
        }

        $items = array_map(
            fn (array $datosItem): ItemVerificacionRecepcion => new ItemVerificacionRecepcion(
                item: $datosItem['item'],
                resultado: $datosItem['resultado'],
            ),
            $input->itemsVerificacion,
        );

        $lote->verificarConforme($items);

        $solicitud = $this->solicitudRepo->buscarPorId($solicitudId);

        $this->transactionManager->executeTransactional(function () use ($lote): void {
            $this->recepcionRepo->guardar($lote);
            foreach ($lote->pullEvents() as $event) {
                $this->eventPublisher->publish($event);
            }
        });

        $notifRef = $this->notificacionInvestigador->notificarRecepcionFinalizada(
            solicitudId: (string) $lote->solicitudId(),
            investigadorId: $solicitud?->investigadorId() ?? '',
            estadoColeccion: $lote->estadoColeccion()->value,
        );

        return AprobarRecepcionLoteOutput::fromPrimitives(
            estado: $lote->estado()->value,
            actaDigitalRecepcionEmitida: $lote->actaEmitida(),
            estadoColeccion: $lote->estadoColeccion()->value,
            notificacionInvestigadorEnviada: $notifRef !== '',
        );
    }
}
