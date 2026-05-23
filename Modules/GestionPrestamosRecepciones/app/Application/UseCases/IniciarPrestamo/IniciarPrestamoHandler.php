<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\IniciarPrestamo;

use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Entities\Prestamo;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\ActaPrestamoNoEncontradaException;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\ActaPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\PrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ActaPrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudPrestamoId;
use RuntimeException;

final class IniciarPrestamoHandler
{
    public function __construct(
        private readonly ActaPrestamoRepositoryInterface $actaRepo,
        private readonly SolicitudPrestamoRepositoryInterface $solicitudRepo,
        private readonly PrestamoRepositoryInterface $prestamoRepo,
        private readonly EventPublisherPort $publisher,
        private readonly TransactionManagerPort $transactionManager,
    ) {}

    public function handle(IniciarPrestamoInput $input): IniciarPrestamoOutput
    {
        $actaId = ActaPrestamoId::fromString($input->actaPrestamoId);
        $acta = $this->actaRepo->buscarPorId($actaId);

        if ($acta === null) {
            throw ActaPrestamoNoEncontradaException::conId($actaId);
        }

        $solicitudId = SolicitudPrestamoId::fromString($input->solicitudId);
        $solicitud = $this->solicitudRepo->buscarPorId($solicitudId);

        if ($solicitud === null) {
            throw new RuntimeException("Solicitud no encontrada: {$input->solicitudId}");
        }

        $prestamo = Prestamo::iniciar(
            id: $this->prestamoRepo->nextIdentity(),
            actaPrestamoId: $actaId,
            investigadorId: $solicitud->investigadorId(),
            iniciadoEn: $acta->fechaInicio(),
            fechaFin: $acta->fechaFin(),
        );

        $this->transactionManager->executeTransactional(function () use ($prestamo): void {
            $this->prestamoRepo->guardar($prestamo);
            foreach ($prestamo->pullEvents() as $event) {
                $this->publisher->publish($event);
            }
        });

        return IniciarPrestamoOutput::fromPrestamo($prestamo);
    }
}
