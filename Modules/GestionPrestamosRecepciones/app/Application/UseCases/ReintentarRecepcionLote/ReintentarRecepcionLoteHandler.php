<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ReintentarRecepcionLote;

use Modules\GestionPrestamosRecepciones\Application\Exceptions\RecepcionLoteNoEncontradaException;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\RecepcionLoteRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;

/**
 * Reabre la verificación de un lote en Recepción Suspendida para evaluarlo de nuevo
 * con el mismo Código QR, una vez que el investigador subsanó la anomalía.
 */
final class ReintentarRecepcionLoteHandler
{
    public function __construct(
        private readonly RecepcionLoteRepositoryInterface $recepcionRepo,
        private readonly TransactionManagerPort $transactionManager,
        private readonly EventPublisherPort $eventPublisher,
    ) {}

    /**
     * @throws RecepcionLoteNoEncontradaException Si el lote no fue iniciado.
     */
    public function __invoke(ReintentarRecepcionLoteInput $input): ReintentarRecepcionLoteOutput
    {
        $lote = $this->recepcionRepo->buscarPorSolicitudId(SolicitudDepositoId::from($input->solicitudId));

        if ($lote === null) {
            throw RecepcionLoteNoEncontradaException::conSolicitud($input->solicitudId);
        }

        $lote->reintentarVerificacion();

        $this->transactionManager->executeTransactional(function () use ($lote): void {
            $this->recepcionRepo->guardar($lote);
            foreach ($lote->pullEvents() as $event) {
                $this->eventPublisher->publish($event);
            }
        });

        return ReintentarRecepcionLoteOutput::fromPrimitives(
            estado: $lote->estado()->value,
            codigoQrVigente: (string) $lote->codigoQR() !== '',
        );
    }
}
