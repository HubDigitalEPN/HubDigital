<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\IniciarRecepcionLote;

use Modules\GestionPrestamosRecepciones\Application\Exceptions\RecepcionNoIniciableException;
use Modules\GestionPrestamosRecepciones\Application\Exceptions\SolicitudNoEncontradaException;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Entities\RecepcionLote;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\RecepcionLoteRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudDepositoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoTramite;

/**
 * Inicia la recepción física del lote entregado por el investigador, tras acceder a
 * la solicitud a partir de su Código QR. Es idempotente: si el lote ya fue iniciado,
 * lo devuelve sin duplicarlo.
 */
final class IniciarRecepcionLoteHandler
{
    public function __construct(
        private readonly SolicitudDepositoRepositoryInterface $solicitudRepo,
        private readonly RecepcionLoteRepositoryInterface $recepcionRepo,
        private readonly TransactionManagerPort $transactionManager,
        private readonly EventPublisherPort $eventPublisher,
    ) {}

    /**
     * @throws SolicitudNoEncontradaException Si la solicitud no existe.
     * @throws RecepcionNoIniciableException Si la solicitud no está aprobada documentalmente.
     */
    public function __invoke(IniciarRecepcionLoteInput $input): IniciarRecepcionLoteOutput
    {
        $solicitudId = SolicitudDepositoId::from($input->solicitudId);
        $solicitud = $this->solicitudRepo->buscarPorId($solicitudId);

        if ($solicitud === null) {
            throw SolicitudNoEncontradaException::conId($input->solicitudId);
        }

        $existente = $this->recepcionRepo->buscarPorSolicitudId($solicitudId);
        if ($existente !== null) {
            return IniciarRecepcionLoteOutput::fromEntity($existente);
        }

        if (! $solicitud->estado()->equals(EstadoSolicitudDeposito::AprobadaDocumentalmente)) {
            throw RecepcionNoIniciableException::solicitudNoAprobada($input->solicitudId);
        }

        $lote = RecepcionLote::iniciar(
            $this->recepcionRepo->nextIdentity(),
            $solicitudId,
            $solicitud->codigoQR(),
            TipoTramite::from($solicitud->tipoTramite()),
        );

        $this->transactionManager->executeTransactional(function () use ($lote): void {
            $this->recepcionRepo->guardar($lote);
            foreach ($lote->pullEvents() as $event) {
                $this->eventPublisher->publish($event);
            }
        });

        return IniciarRecepcionLoteOutput::fromEntity($lote);
    }
}
