<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarRecepcionLote;

use Modules\GestionPrestamosRecepciones\Application\Exceptions\RecepcionLoteNoEncontradaException;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\NotificacionCuratoriaPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\NotificacionInvestigadorPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\RecepcionLoteRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudDepositoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ItemVerificacionRecepcion;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;

/**
 * Aprueba la recepción de un lote que cumple todos los ítems de la lista de
 * verificación: lo deja Verificado Físicamente, fija el régimen de tenencia
 * propuesto y notifica al depositante que EPN recibió el material. Curaduría debe
 * generar y firmar el acta antes del alta en colección.
 */
final class AprobarRecepcionLoteHandler
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
    public function __invoke(AprobarRecepcionLoteInput $input): AprobarRecepcionLoteOutput
    {
        $solicitudId = SolicitudDepositoId::from($input->solicitudId);
        $lote = $this->recepcionRepo->buscarPorSolicitudId($solicitudId);

        if ($lote === null) {
            throw RecepcionLoteNoEncontradaException::conSolicitud($input->solicitudId);
        }

        $solicitud = $this->solicitudRepo->buscarPorId($solicitudId);
        $solicitud?->asegurarContenidoRecepcionValidado();

        $items = array_map(
            fn (array $datosItem): ItemVerificacionRecepcion => new ItemVerificacionRecepcion(
                item: $datosItem['item'],
                resultado: $datosItem['resultado'],
            ),
            $input->itemsVerificacion,
        );

        $lote->verificarConforme($items, $input->receptorId);

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
        $this->notificacionCuratoria->notificarLoteRecibidoParaActa(
            solicitudId: (string) $lote->solicitudId(),
            receptorId: $input->receptorId,
            conObservaciones: false,
        );

        return AprobarRecepcionLoteOutput::fromPrimitives(
            estado: $lote->estado()->value,
            actaDigitalRecepcionEmitida: $lote->actaEmitida(),
            estadoColeccion: $lote->estadoColeccion()->value,
            notificacionInvestigadorEnviada: $notifRef !== '',
        );
    }
}
