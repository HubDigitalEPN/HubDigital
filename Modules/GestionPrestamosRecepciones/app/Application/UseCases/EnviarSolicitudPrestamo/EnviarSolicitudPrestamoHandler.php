<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\EnviarSolicitudPrestamo;

use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\SolicitudPrestamoNoEncontradaException;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudPrestamoId;

/**
 * Manejador del caso de uso para enviar una solicitud de préstamo.
 * 
 * {@see EnviarSolicitudPrestamoInput}
 * {@see EnviarSolicitudPrestamoOutput}
 */
final class EnviarSolicitudPrestamoHandler
{
    /**
     * @param SolicitudPrestamoRepositoryInterface $repo Repositorio de solicitudes de préstamo.
     * @param EventPublisherPort $publisher Publicador de eventos.
     * @param TransactionManagerPort $transactionManager Gestor de transacciones.
     */
    public function __construct(
        private readonly SolicitudPrestamoRepositoryInterface $repo,
        private readonly EventPublisherPort $publisher,
        private readonly TransactionManagerPort $transactionManager,
    ) {}

    /**
     * Ejecuta el caso de uso.
     *
     * @param EnviarSolicitudPrestamoInput $input Datos de entrada.
     * @return EnviarSolicitudPrestamoOutput Resultado del envío.
     * @throws SolicitudPrestamoNoEncontradaException Si la solicitud no existe.
     */
    public function handle(EnviarSolicitudPrestamoInput $input): EnviarSolicitudPrestamoOutput
    {
        $id        = SolicitudPrestamoId::fromString($input->solicitudId);
        $solicitud = $this->repo->buscarPorId($id);

        if ($solicitud === null) {
            throw SolicitudPrestamoNoEncontradaException::conId($id);
        }

        $solicitud->enviar();

        $this->transactionManager->executeTransactional(function () use ($solicitud): void {
            $this->repo->guardar($solicitud);
            foreach ($solicitud->pullEvents() as $event) {
                $this->publisher->publish($event);
            }
        });

        return EnviarSolicitudPrestamoOutput::fromPrimitives($solicitud);
    }
}
