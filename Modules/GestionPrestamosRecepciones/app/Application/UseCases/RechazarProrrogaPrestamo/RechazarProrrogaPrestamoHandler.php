<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\RechazarProrrogaPrestamo;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\PrestamoNoEncontradoException;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\PrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudProrrogaRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;

/**
 * Caso de uso: el curador rechaza una solicitud de prórroga pendiente.
 *
 * El préstamo vuelve a estado Activo conservando su fecha de fin original. Requiere
 * un comentario obligatorio que justifique el rechazo.
 */
final class RechazarProrrogaPrestamoHandler
{
    public function __construct(
        private readonly PrestamoRepositoryInterface $prestamoRepo,
        private readonly SolicitudProrrogaRepositoryInterface $solicitudRepo,
        private readonly EventPublisherPort $publisher,
        private readonly TransactionManagerPort $transactionManager,
    ) {}

    /**
     * @throws PrestamoNoEncontradoException Si el préstamo no existe.
     * @throws \Modules\GestionPrestamosRecepciones\Domain\Exceptions\TransicionDeEstadoInvalidaException Si el préstamo no tiene una prórroga solicitada.
     * @throws \InvalidArgumentException Si falta el comentario.
     */
    public function handle(RechazarProrrogaPrestamoInput $input): RechazarProrrogaPrestamoOutput
    {
        $prestamoId = PrestamoId::fromString($input->prestamoId);
        $prestamo = $this->prestamoRepo->buscarPorId($prestamoId);

        if ($prestamo === null) {
            throw PrestamoNoEncontradoException::conId($prestamoId);
        }

        $ahora = new DateTimeImmutable;

        // El comentario obligatorio se valida en el agregado de la solicitud antes
        // de mutar el préstamo: si falta, nada se persiste y el préstamo permanece
        // en "prórroga solicitada".
        $solicitud = $this->solicitudRepo->buscarPendientePorPrestamo($prestamo->id());
        $solicitud?->rechazar($input->comentario, $ahora);

        $prestamo->rechazarProrroga($input->curadorId, $input->comentario);

        $this->transactionManager->executeTransactional(function () use ($prestamo, $solicitud): void {
            $this->prestamoRepo->guardar($prestamo);
            if ($solicitud !== null) {
                $this->solicitudRepo->guardar($solicitud);
            }
            foreach ($prestamo->pullEvents() as $event) {
                $this->publisher->publish($event);
            }
        });

        return RechazarProrrogaPrestamoOutput::from($prestamo);
    }
}
