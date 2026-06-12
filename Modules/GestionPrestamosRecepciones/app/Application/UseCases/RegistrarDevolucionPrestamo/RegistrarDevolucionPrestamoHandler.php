<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\RegistrarDevolucionPrestamo;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\PrestamoNoEncontradoException;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\PrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;

/**
 * Caso de uso: El investigador notifica que ha enviado los especímenes de vuelta.
 *
 * Transiciona el préstamo de Activo a EnRevision para que el curador
 * proceda con la verificación de la devolución.
 */
final class RegistrarDevolucionPrestamoHandler
{
    public function __construct(
        private readonly PrestamoRepositoryInterface $prestamoRepo,
        private readonly EventPublisherPort $publisher,
        private readonly TransactionManagerPort $transactionManager,
    ) {}

    /**
     * @throws PrestamoNoEncontradoException Si el préstamo no existe.
     * @throws \Modules\GestionPrestamosRecepciones\Domain\Exceptions\TransicionDeEstadoInvalidaException Si el préstamo no está activo.
     */
    public function handle(RegistrarDevolucionPrestamoInput $input): RegistrarDevolucionPrestamoOutput
    {
        $prestamoId = PrestamoId::fromString($input->prestamoId);
        $prestamo = $this->prestamoRepo->buscarPorId($prestamoId);

        if ($prestamo === null) {
            throw PrestamoNoEncontradoException::conId($prestamoId);
        }

        $prestamo->registrarDevolucion(
            investigadorId: $input->investigadorId,
            ahora: new DateTimeImmutable,
        );

        $this->transactionManager->executeTransactional(function () use ($prestamo): void {
            $this->prestamoRepo->guardar($prestamo);
            foreach ($prestamo->pullEvents() as $event) {
                $this->publisher->publish($event);
            }
        });

        return RegistrarDevolucionPrestamoOutput::fromPrestamo($prestamo);
    }
}
