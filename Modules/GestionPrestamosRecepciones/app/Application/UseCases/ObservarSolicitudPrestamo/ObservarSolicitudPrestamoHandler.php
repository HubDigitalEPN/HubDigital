<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ObservarSolicitudPrestamo;

use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\SolicitudPrestamoNoEncontradaException;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudPrestamoId;

final class ObservarSolicitudPrestamoHandler
{
    public function __construct(
        private readonly SolicitudPrestamoRepositoryInterface $solicitudRepo,
        private readonly EventPublisherPort $publisher,
        private readonly TransactionManagerPort $transactionManager,
    ) {}

    public function handle(ObservarSolicitudPrestamoInput $input): ObservarSolicitudPrestamoOutput
    {
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
