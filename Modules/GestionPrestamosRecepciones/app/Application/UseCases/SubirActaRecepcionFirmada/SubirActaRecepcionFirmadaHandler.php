<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\SubirActaRecepcionFirmada;

use Illuminate\Support\Facades\Log;
use Modules\GestionPrestamosRecepciones\Application\Exceptions\RecepcionLoteNoEncontradaException;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\NotificacionInvestigadorPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\ValidacionFirmaElectronicaPort;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\ActaRecepcionSinFirmaElectronica;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\RecepcionLoteRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudDepositoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;

/**
 * Adjunta el PDF producido por el firmador local de HubDigital. Antes de persistirlo,
 * verifica la firma criptográfica y compara su contenido con el documento oficial.
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

        $validacion = $this->validadorFirma->verificarFirmaDetallada(
            $input->rutaAbsoluta,
            $input->rutaOriginalAbsoluta,
        );

        if (! $validacion->esAceptable()) {
            throw ActaRecepcionSinFirmaElectronica::crear();
        }

        $firmanteId = trim($input->curadorId);
        if ($firmanteId === '') {
            throw new \InvalidArgumentException('El curador firmante es obligatorio.');
        }

        $firmaMetadata = $validacion->toArray();
        $firmaMetadata['firmante_usuario_id'] = $firmanteId;
        $firmaMetadata['proposito'] = 'acta_final_recepcion';
        $firmaMetadata['pdf_sha256'] = hash_file('sha256', $input->rutaAbsoluta);
        $firmaMetadata['original_referencia'] = $input->referenciaOriginal;
        $firmaMetadata['original_sha256'] = $input->sha256Original;

        $loteFirmado = null;
        $this->transactionManager->executeTransactional(function () use ($solicitudId, $input, $firmaMetadata, &$loteFirmado): void {
            // La validación criptográfica se hace antes del bloqueo porque puede ser
            // costosa. Dentro de la transacción se vuelve a leer el lote con
            // SELECT ... FOR UPDATE para garantizar un único cierre definitivo.
            $loteActual = $this->recepcionRepo->buscarPorSolicitudIdParaActualizar($solicitudId);
            if ($loteActual === null) {
                throw RecepcionLoteNoEncontradaException::conSolicitud($input->solicitudId);
            }

            if (! $this->recepcionRepo->coincideOriginalVigenteParaActualizar(
                $solicitudId,
                $input->referenciaOriginal,
                $input->sha256Original,
            )) {
                throw new \DomainException('El original oficial cambió durante la firma. Revise y firme la versión vigente.');
            }

            $loteActual->adjuntarActaFirmada($input->rutaRelativa, $firmaMetadata);
            $this->recepcionRepo->guardar($loteActual);
            foreach ($loteActual->pullEvents() as $event) {
                $this->eventPublisher->publish($event);
            }

            $loteFirmado = $loteActual;
        });

        if (! $loteFirmado instanceof \Modules\GestionPrestamosRecepciones\Domain\Entities\RecepcionLote) {
            throw new \LogicException('No se pudo cerrar el expediente con el acta firmada.');
        }

        // Avisar al depositante que el acta firmada ya está disponible para descargar.
        $solicitud = $this->solicitudRepo->buscarPorId($solicitudId);
        if ($solicitud !== null) {
            try {
                $this->notificacionInvestigador->notificarActaRecepcionDisponible(
                    solicitudId: $input->solicitudId,
                    investigadorId: $solicitud->investigadorId(),
                );
            } catch (\Throwable $e) {
                // La entrega del correo/aviso es reintentable y no debe invalidar
                // un acta que ya superó la validación criptográfica y fue persistida.
                Log::error('No se pudo notificar el acta de recepción firmada', [
                    'solicitud_id' => $input->solicitudId,
                    'investigador_id' => $solicitud->investigadorId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return SubirActaRecepcionFirmadaOutput::fromEntity($loteFirmado);
    }
}
