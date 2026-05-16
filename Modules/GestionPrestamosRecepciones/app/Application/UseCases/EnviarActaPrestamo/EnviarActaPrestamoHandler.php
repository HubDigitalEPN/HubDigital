<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\EnviarActaPrestamo;

use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\ActaPrestamoNoEncontradaException;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\SolicitudPrestamoNoEncontradaException;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\ActaPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ActaPrestamoId;

final class EnviarActaPrestamoHandler
{
    public function __construct(
        private readonly ActaPrestamoRepositoryInterface $actaRepo,
        private readonly SolicitudPrestamoRepositoryInterface $solicitudRepo,
        private readonly EventPublisherPort $publisher,
        private readonly TransactionManagerPort $transactionManager,
    ) {}

    public function handle(EnviarActaPrestamoInput $input): EnviarActaPrestamoOutput
    {
        $actaId = ActaPrestamoId::fromString($input->actaId);
        $acta = $this->actaRepo->buscarPorId($actaId);

        if ($acta === null) {
            throw ActaPrestamoNoEncontradaException::conId($actaId);
        }

        $solicitud = $this->solicitudRepo->buscarPorId($acta->solicitudPrestamoId());

        if ($solicitud === null) {
            throw SolicitudPrestamoNoEncontradaException::paraSolicitud($acta->solicitudPrestamoId());
        }

        $acta->marcarEnviada($solicitud->investigadorId());

        $this->transactionManager->executeTransactional(function () use ($acta): void {
            $this->actaRepo->guardar($acta);
            foreach ($acta->pullEvents() as $event) {
                $this->publisher->publish($event);
            }
        });

        return EnviarActaPrestamoOutput::fromPrimitives($acta, notificacionEnviada: true);
    }
}
