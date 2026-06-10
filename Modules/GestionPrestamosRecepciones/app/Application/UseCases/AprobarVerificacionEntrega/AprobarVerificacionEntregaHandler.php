<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarVerificacionEntrega;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\PrestamoNoEncontradoException;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\PrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;

/**
 * Caso de uso: Aprueba la verificación de entrega de un préstamo.
 *
 * El curador valida que los especímenes recibidos por el investigador coinciden
 * con lo estipulado. Al aprobar, el préstamo transiciona al estado Activo.
 */
final class AprobarVerificacionEntregaHandler
{
    public function __construct(
        private readonly PrestamoRepositoryInterface $prestamoRepo,
        private readonly EventPublisherPort $publisher,
        private readonly TransactionManagerPort $transactionManager,
    ) {}

    /**
     * Ejecuta el caso de uso.
     *
     * @param  AprobarVerificacionEntregaInput  $input
     * @return AprobarVerificacionEntregaOutput
     *
     * @throws PrestamoNoEncontradoException Si el préstamo no existe.
     * @throws \Modules\GestionPrestamosRecepciones\Domain\Exceptions\TransicionDeEstadoInvalidaException Si el préstamo no está en el estado adecuado.
     */
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
