<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ObservarSolicitudPrestamo;

use InvalidArgumentException;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\SolicitudPrestamoNoEncontradaException;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudPrestamoId;

/**
 * Observa una solicitud de préstamo indicando las correcciones necesarias.
 *
 * {@see ObservarSolicitudPrestamoInput}
 * {@see ObservarSolicitudPrestamoOutput}
 */
final class ObservarSolicitudPrestamoHandler
{
    public function __construct(
        private readonly SolicitudPrestamoRepositoryInterface $solicitudRepo,
        private readonly EventPublisherPort $publisher,
        private readonly TransactionManagerPort $transactionManager,
    ) {}

    /**
     * @param ObservarSolicitudPrestamoInput $input
     * @return ObservarSolicitudPrestamoOutput
     * @throws InvalidArgumentException
     * @throws SolicitudPrestamoNoEncontradaException
     */
    public function handle(ObservarSolicitudPrestamoInput $input): ObservarSolicitudPrestamoOutput
    {
        if (trim($input->observacion) === '') {
            throw new InvalidArgumentException('La observación del curador no puede estar vacía.');
        }

        $solicitudId = SolicitudPrestamoId::fromString($input->solicitudId);
        $solicitud = $this->solicitudRepo->buscarPorId($solicitudId);

        if ($solicitud === null) {
            throw SolicitudPrestamoNoEncontradaException::paraSolicitud($solicitudId);
        }

        $solicitud->observar(
            curadorId: $input->curadorId,
            observacion: $input->observacion,
        );

        $this->transactionManager->executeTransactional(function () use ($solicitud): void {
            $this->solicitudRepo->guardar($solicitud);
            foreach ($solicitud->pullEvents() as $event) {
                $this->publisher->publish($event);
            }
        });

        return ObservarSolicitudPrestamoOutput::fromPrimitives($solicitud);
    }
}
