<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ActualizarOrigenSolicitudDeposito;

use Modules\GestionPrestamosRecepciones\Application\Exceptions\SolicitudNoEncontradaException;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudDepositoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;

/**
 * Caso de uso: Actualiza la información de origen de una solicitud de depósito.
 * 
 * {@see ActualizarOrigenSolicitudDepositoInput}
 * {@see ActualizarOrigenSolicitudDepositoOutput}
 */
final class ActualizarOrigenSolicitudDepositoHandler
{
    /**
     * Crea una nueva instancia de ActualizarOrigenSolicitudDepositoHandler.
     * @param SolicitudDepositoRepositoryInterface $repo Repositorio de solicitudes de depósito.
     * @param TransactionManagerPort $transactionManager Puerto para la gestión de transacciones.
     * @param EventPublisherPort $eventPublisher Puerto para la publicación de eventos.
     */
    public function __construct(
        private SolicitudDepositoRepositoryInterface $repo,
        private TransactionManagerPort $transactionManager,
        private EventPublisherPort $eventPublisher,
    ) {}

    /**
     * Ejecuta el caso de uso.
     * @param ActualizarOrigenSolicitudDepositoInput $input
     * @return ActualizarOrigenSolicitudDepositoOutput
     * @throws SolicitudNoEncontradaException Si no se encuentra la solicitud de depósito.
     */
    public function __invoke(ActualizarOrigenSolicitudDepositoInput $input): ActualizarOrigenSolicitudDepositoOutput
    {
        $id = SolicitudDepositoId::from($input->solicitudId);
        $solicitud = $this->repo->buscarPorId($id);

        if ($solicitud === null) {
            throw SolicitudNoEncontradaException::conId($input->solicitudId);
        }

        $solicitud->declararOrigenRecoleccion($input->origenRecoleccion);
        $solicitud->declararSituacionRegulatoria($input->situacionRegulatoria);

        if (! empty($input->provinciaOrigen)) {
            $solicitud->declararProvincia($input->provinciaOrigen);
        }

        $this->transactionManager->executeTransactional(function () use ($solicitud): void {
            $this->repo->guardar($solicitud);
            foreach ($solicitud->pullEvents() as $event) {
                $this->eventPublisher->publish($event);
            }
        });

        return ActualizarOrigenSolicitudDepositoOutput::fromEntity($solicitud);
    }
}
