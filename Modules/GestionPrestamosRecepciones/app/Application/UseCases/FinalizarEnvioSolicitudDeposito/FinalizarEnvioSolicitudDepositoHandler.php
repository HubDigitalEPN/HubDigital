<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\FinalizarEnvioSolicitudDeposito;

use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\MatrizEspeciesRequeridaException;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\MatrizEspeciesRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudDepositoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoMatrizEspecies;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;

/**
 * Manejador del caso de uso para finalizar el envío de una solicitud de depósito.
 * 
 * {@see FinalizarEnvioSolicitudDepositoInput}
 * {@see FinalizarEnvioSolicitudDepositoOutput}
 */
final class FinalizarEnvioSolicitudDepositoHandler
{
    /**
     * @param SolicitudDepositoRepositoryInterface $solicitudRepo Repositorio de solicitudes de depósito.
     * @param MatrizEspeciesRepositoryInterface $matrizRepo Repositorio de matrices de especies.
     * @param TransactionManagerPort $transactionManager Gestor de transacciones.
     * @param EventPublisherPort $eventPublisher Publicador de eventos.
     */
    public function __construct(
        private SolicitudDepositoRepositoryInterface $solicitudRepo,
        private MatrizEspeciesRepositoryInterface $matrizRepo,
        private TransactionManagerPort $transactionManager,
        private EventPublisherPort $eventPublisher,
    ) {}

    /**
     * Ejecuta el caso de uso.
     *
     * @param FinalizarEnvioSolicitudDepositoInput $input Datos de entrada.
     * @return FinalizarEnvioSolicitudDepositoOutput Resultado de la finalización del envío.
     * @throws \DomainException Si la solicitud no es encontrada.
     * @throws MatrizEspeciesRequeridaException Si la matriz de especies es requerida pero no se encontró.
     */
    public function __invoke(FinalizarEnvioSolicitudDepositoInput $input): FinalizarEnvioSolicitudDepositoOutput
    {
        $solicitudId = SolicitudDepositoId::from($input->solicitudId);
        $solicitud = $this->solicitudRepo->buscarPorId($solicitudId);

        if ($solicitud === null) {
            throw new \DomainException(
                sprintf('No se encontró la solicitud con ID "%s"', $input->solicitudId)
            );
        }

        $matriz = $this->matrizRepo->buscarPorSolicitudId($input->solicitudId);

        if ($matriz === null) {
            throw MatrizEspeciesRequeridaException::paraFinalizar();
        }

        $alertasDerivadasACuraduria = $matriz->estado()->equals(EstadoMatrizEspecies::CargadaConAlertas)
            && $matriz->todosLosHallazgosJustificados();

        $this->transactionManager->executeTransactional(function () use ($solicitud): void {
            $this->solicitudRepo->guardar($solicitud);

            foreach ($solicitud->pullEvents() as $event) {
                $this->eventPublisher->publish($event);
            }
        });

        return new FinalizarEnvioSolicitudDepositoOutput(
            enviada: true,
            alertasDerivadasACuraduria: $alertasDerivadasACuraduria,
            solicitudId: $input->solicitudId,
        );
    }
}
