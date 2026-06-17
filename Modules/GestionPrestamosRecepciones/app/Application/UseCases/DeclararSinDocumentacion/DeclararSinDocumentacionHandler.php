<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\DeclararSinDocumentacion;

use Modules\GestionPrestamosRecepciones\Application\Exceptions\SolicitudNoEncontradaException;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudDepositoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;

/**
 * Manejador del caso de uso para declarar una solicitud sin documentación.
 * 
 * {@see DeclararSinDocumentacionInput}
 * {@see DeclararSinDocumentacionOutput}
 */
final class DeclararSinDocumentacionHandler
{
    /**
     * @param SolicitudDepositoRepositoryInterface $repo Repositorio de solicitudes de depósito.
     * @param TransactionManagerPort $transactionManager Gestor de transacciones.
     * @param EventPublisherPort $eventPublisher Publicador de eventos de dominio.
     */
    public function __construct(
        private SolicitudDepositoRepositoryInterface $repo,
        private TransactionManagerPort $transactionManager,
        private EventPublisherPort $eventPublisher,
    ) {}

    /**
     * Ejecuta el caso de uso.
     *
     * @param DeclararSinDocumentacionInput $input Datos de entrada.
     * @return DeclararSinDocumentacionOutput Resultado del caso de uso.
     * @throws SolicitudNoEncontradaException Si la solicitud no existe.
     */
    public function __invoke(DeclararSinDocumentacionInput $input): DeclararSinDocumentacionOutput
    {
        $id = SolicitudDepositoId::from($input->solicitudId);
        $solicitud = $this->repo->buscarPorId($id);

        if ($solicitud === null) {
            throw SolicitudNoEncontradaException::conId($input->solicitudId);
        }

        $solicitud->marcarSinDocumentacionDisponible();

        $this->transactionManager->executeTransactional(function () use ($solicitud): void {
            $this->repo->guardar($solicitud);
            foreach ($solicitud->pullEvents() as $event) {
                $this->eventPublisher->publish($event);
            }
        });

        return new DeclararSinDocumentacionOutput(sinDocumentacion: true);
    }
}
