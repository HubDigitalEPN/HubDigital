<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\SubirActaRecepcionFirmada;

use Modules\GestionPrestamosRecepciones\Application\Exceptions\RecepcionLoteNoEncontradaException;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\NotificacionInvestigadorPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\ValidacionFirmaElectronicaPort;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\ActaRecepcionSinFirmaElectronica;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\RecepcionLoteRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudDepositoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ResultadoValidacionFirma;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;

/**
 * Adjunta al acta de recepción el PDF firmado electrónicamente (FirmaEC) por el curador.
 * Antes de persistirlo, verifica con pdfsig que el documento traiga una firma electrónica
 * válida; si no la tiene, rechaza la subida.
 */
final class SubirActaRecepcionFirmadaHandler
{
    public function __construct(
        private readonly RecepcionLoteRepositoryInterface $recepcionRepo,
        private readonly SolicitudDepositoRepositoryInterface $solicitudRepo,
        private readonly ValidacionFirmaElectronicaPort $validadorFirma,
        private readonly TransactionManagerPort $transactionManager,
        private readonly EventPublisherPort $eventPublisher,
        private readonly NotificacionInvestigadorPort $notificacionInvestigador,
    ) {}

    /**
     * @throws RecepcionLoteNoEncontradaException Si el lote no fue iniciado.
     * @throws ActaRecepcionSinFirmaElectronica Si el PDF no trae firma electrónica válida.
     */
    public function __invoke(SubirActaRecepcionFirmadaInput $input): SubirActaRecepcionFirmadaOutput
    {
        $solicitudId = SolicitudDepositoId::from($input->solicitudId);
        $lote = $this->recepcionRepo->buscarPorSolicitudId($solicitudId);

        if ($lote === null) {
            throw RecepcionLoteNoEncontradaException::conSolicitud($input->solicitudId);
        }

        if ($this->validadorFirma->verificarFirma($input->rutaAbsoluta) !== ResultadoValidacionFirma::Firmado) {
            throw ActaRecepcionSinFirmaElectronica::crear();
        }

        $lote->adjuntarActaFirmada($input->rutaRelativa);

        $this->transactionManager->executeTransactional(function () use ($lote): void {
            $this->recepcionRepo->guardar($lote);
            foreach ($lote->pullEvents() as $event) {
                $this->eventPublisher->publish($event);
            }
        });

        // Avisar al depositante que el acta firmada ya está disponible para descargar.
        $solicitud = $this->solicitudRepo->buscarPorId($solicitudId);
        if ($solicitud !== null) {
            $this->notificacionInvestigador->notificarActaRecepcionDisponible(
                solicitudId: $input->solicitudId,
                investigadorId: $solicitud->investigadorId(),
            );
        }

        return SubirActaRecepcionFirmadaOutput::fromEntity($lote);
    }
}
