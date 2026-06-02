<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarVerificacionEntrega;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\PrestamoNoEncontradoException;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\PrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;

final class AprobarVerificacionEntregaHandler
{
    public function __construct(
        private readonly PrestamoRepositoryInterface $prestamoRepo,
        private readonly EventPublisherPort $publisher,
        private readonly TransactionManagerPort $transactionManager,
    ) {}

    public function handle(AprobarVerificacionEntregaInput $input): AprobarVerificacionEntregaOutput
    {
        $prestamoId = PrestamoId::fromString($input->prestamoId);
        $prestamo = $this->prestamoRepo->buscarPorId($prestamoId);

        if ($prestamo === null) {
            throw PrestamoNoEncontradoException::conId($prestamoId);
        }

        $prestamo->aprobarVerificacion(
            curadorId: $input->curadorId,
            ahora: new DateTimeImmutable,
        );

        $this->transactionManager->executeTransactional(function () use ($prestamo): void {
            $this->prestamoRepo->guardar($prestamo);
            foreach ($prestamo->pullEvents() as $event) {
                $this->publisher->publish($event);
            }
        });

        return AprobarVerificacionEntregaOutput::fromPrestamo($prestamo);
    }
}
