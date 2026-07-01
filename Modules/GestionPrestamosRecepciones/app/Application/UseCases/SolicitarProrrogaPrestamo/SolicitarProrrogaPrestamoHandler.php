<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\SolicitarProrrogaPrestamo;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudProrroga;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\PrestamoNoEncontradoException;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\PrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudProrrogaRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;

/**
 * Caso de uso: el investigador solicita una prórroga de un préstamo activo.
 *
 * Deja el préstamo en estado "prórroga solicitada" y registra la petición (fecha
 * propuesta y justificación) a la espera de la resolución del curador. No se permite
 * sobre un préstamo vencido ni sin justificación.
 */
final class SolicitarProrrogaPrestamoHandler
{
    public function __construct(
        private readonly PrestamoRepositoryInterface $prestamoRepo,
        private readonly SolicitudProrrogaRepositoryInterface $solicitudRepo,
        private readonly EventPublisherPort $publisher,
        private readonly TransactionManagerPort $transactionManager,
    ) {}

    /**
     * @throws PrestamoNoEncontradoException Si el préstamo no existe.
     * @throws \Modules\GestionPrestamosRecepciones\Domain\Exceptions\TransicionDeEstadoInvalidaException Si el préstamo no está activo.
     * @throws \InvalidArgumentException Si falta la justificación.
     */
    public function handle(SolicitarProrrogaPrestamoInput $input): SolicitarProrrogaPrestamoOutput
    {
        $prestamoId = PrestamoId::fromString($input->prestamoId);
        $prestamo = $this->prestamoRepo->buscarPorId($prestamoId);

        if ($prestamo === null) {
            throw PrestamoNoEncontradoException::conId($prestamoId);
        }

        $ahora = new DateTimeImmutable;

        // Construir (y validar) la solicitud ANTES de mutar el préstamo: si la
        // justificación falta, la excepción se lanza sin haber alterado el estado
        // del agregado préstamo.
        $solicitud = SolicitudProrroga::solicitar(
            id: $this->solicitudRepo->nextIdentity(),
            prestamoId: $prestamo->id(),
            fechaPropuesta: new DateTimeImmutable($input->nuevaFechaFin),
            justificacion: $input->justificacion,
            solicitadaEn: $ahora,
        );

        $prestamo->solicitarProrroga($ahora);

        $this->transactionManager->executeTransactional(function () use ($prestamo, $solicitud): void {
            $this->prestamoRepo->guardar($prestamo);
            $this->solicitudRepo->guardar($solicitud);
            foreach ($prestamo->pullEvents() as $event) {
                $this->publisher->publish($event);
            }
        });

        return SolicitarProrrogaPrestamoOutput::from($prestamo, $solicitud);
    }
}
