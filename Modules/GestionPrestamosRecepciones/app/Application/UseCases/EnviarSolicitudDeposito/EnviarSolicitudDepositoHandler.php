<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\EnviarSolicitudDeposito;

use Modules\GestionPrestamosRecepciones\Application\Exceptions\SolicitudNoEncontradaException;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\NotificacionCuratoriaPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\SolicitudFirmadaPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\MatrizEspeciesRequeridaException;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\SolicitudDepositoYaProcesada;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\MatrizEspeciesRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudDepositoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Services\ReglaDocumentacionRequerida;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;

/**
 * Manejador del caso de uso para enviar una solicitud de depósito a revisión por curaduría.
 *
 * {@see EnviarSolicitudDepositoInput}
 * {@see EnviarSolicitudDepositoOutput}
 */
final class EnviarSolicitudDepositoHandler
{
    /**
     * @param  SolicitudDepositoRepositoryInterface  $repo  Repositorio de solicitudes de depósito.
     * @param  TransactionManagerPort  $transactionManager  Gestor de transacciones.
     * @param  EventPublisherPort  $eventPublisher  Publicador de eventos.
     * @param  NotificacionCuratoriaPort  $notificacionCuratoria  Notificador de la curaduría.
     */
    public function __construct(
        private SolicitudDepositoRepositoryInterface $repo,
        private TransactionManagerPort $transactionManager,
        private EventPublisherPort $eventPublisher,
        private NotificacionCuratoriaPort $notificacionCuratoria,
        private SolicitudFirmadaPort $solicitudFirmada,
        private MatrizEspeciesRepositoryInterface $matrizRepo,
    ) {}

    /**
     * Ejecuta el caso de uso.
     *
     * @param  EnviarSolicitudDepositoInput  $input  Datos de entrada.
     * @return EnviarSolicitudDepositoOutput Estado de la solicitud enviada.
     *
     * @throws SolicitudNoEncontradaException Si la solicitud no existe.
     */
    public function __invoke(EnviarSolicitudDepositoInput $input): EnviarSolicitudDepositoOutput
    {
        $id = SolicitudDepositoId::from($input->solicitudId);

        if (! $this->solicitudFirmada->estaFirmada($input->solicitudId)) {
            throw new \DomainException(
                'Debes generar y firmar electrónicamente la solicitud oficial antes de enviarla.'
            );
        }

        $solicitud = null;
        $this->transactionManager->executeTransactional(function () use ($id, $input, &$solicitud): void {
            $solicitud = $this->repo->buscarPorIdParaActualizar($id);
            if ($solicitud === null) {
                throw SolicitudNoEncontradaException::conId($input->solicitudId);
            }

            if ($solicitud->estado() === EstadoSolicitudDeposito::PendienteDeRevisionPorCuraduria) {
                throw SolicitudDepositoYaProcesada::enviada();
            }

            if ($solicitud->finalidadActividad() !== null) {
                (new ReglaDocumentacionRequerida)->determinar(
                    $solicitud->tipoTramite(), $solicitud->origenRecoleccion() ?? '', $solicitud->situacionRegulatoria() ?? '',
                    $solicitud->provinciaOrigen(), $solicitud->finalidadActividad(),
                );
                if ($solicitud->origenRecoleccion() === 'Nacional (Ecuador)'
                    && (! filled($solicitud->localidadOrigenCodigo()) || ! filled($solicitud->localidad()))) {
                    throw new \DomainException('Selecciona la localidad de recolección del catálogo INEC.');
                }
                foreach (['N.º Individuos', 'N.º Morfoespecies', 'N.º Lotes'] as $campo) {
                    if (! in_array($campo, $solicitud->datosIngresadosManualmenterParaPersistir(), true)) {
                        throw new \DomainException('Declara las cantidades del material antes de enviar la solicitud.');
                    }
                }
            }

            $matriz = $this->matrizRepo->buscarPorSolicitudId($input->solicitudId);
            if ($matriz === null) {
                throw MatrizEspeciesRequeridaException::paraFinalizar();
            }
            if (! $matriz->estaCompletaParaEnvio()) {
                throw MatrizEspeciesRequeridaException::incompletaParaFinalizar();
            }

            $solicitud->avanzarARevisionCuraduria();
            if ($solicitud->finalidadActividad() !== null
                && $matriz->incorporarPermisosDelExpediente($solicitud->nroPermisoRecoleccion(), $solicitud->nroPermisoMovilizacion())) {
                $this->matrizRepo->guardar($matriz);
            }
            $this->repo->guardar($solicitud);
            foreach ($solicitud->pullEvents() as $event) {
                $this->eventPublisher->publish($event);
            }
        });

        $curadorRef = $this->notificacionCuratoria->notificarNuevaSolicitudPorRevisar((string) $solicitud->id());

        return EnviarSolicitudDepositoOutput::fromPrimitives(
            estado: $solicitud->estado()->value,
            notificacionCuradorEnviada: $curadorRef !== '',
        );
    }
}
