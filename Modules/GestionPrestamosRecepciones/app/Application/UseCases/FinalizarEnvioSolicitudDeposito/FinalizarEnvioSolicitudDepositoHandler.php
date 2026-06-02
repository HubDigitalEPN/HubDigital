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

final class FinalizarEnvioSolicitudDepositoHandler
{
    public function __construct(
        private SolicitudDepositoRepositoryInterface $solicitudRepo,
        private MatrizEspeciesRepositoryInterface $matrizRepo,
        private TransactionManagerPort $transactionManager,
        private EventPublisherPort $eventPublisher,
    ) {}

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
