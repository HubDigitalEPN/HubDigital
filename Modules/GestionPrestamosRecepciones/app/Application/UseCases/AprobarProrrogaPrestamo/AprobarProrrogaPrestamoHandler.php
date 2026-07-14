<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarProrrogaPrestamo;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Entities\RecordatorioDevolucion;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\PrestamoNoEncontradoException;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\PrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\RecordatorioDevolucionRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudProrrogaRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;

/**
 * Caso de uso: Aprueba una solicitud de prórroga pendiente de un préstamo.
 *
 * El curador, sobre un préstamo en estado "prórroga solicitada", extiende su fecha
 * de fin y lo devuelve a Activo. También marca la solicitud como aprobada y
 * reprograma los recordatorios de devolución según la nueva fecha.
 */
final class AprobarProrrogaPrestamoHandler
{
    public function __construct(
        private readonly PrestamoRepositoryInterface $prestamoRepo,
        private readonly SolicitudProrrogaRepositoryInterface $solicitudRepo,
        private readonly RecordatorioDevolucionRepositoryInterface $recordatorioRepo,
        private readonly EventPublisherPort $publisher,
        private readonly TransactionManagerPort $transactionManager,
    ) {}

    /**
     * Ejecuta el caso de uso.
     *
     * @param  AprobarProrrogaPrestamoInput  $input
     * @return AprobarProrrogaPrestamoOutput
     *
     * @throws PrestamoNoEncontradoException Si el préstamo no existe.
     * @throws \Modules\GestionPrestamosRecepciones\Domain\Exceptions\TransicionDeEstadoInvalidaException Si la nueva fecha no es válida.
     */
    public function handle(AprobarProrrogaPrestamoInput $input): AprobarProrrogaPrestamoOutput
    {
        $prestamoId = PrestamoId::fromString($input->prestamoId);
        $prestamo = $this->prestamoRepo->buscarPorId($prestamoId);

        if ($prestamo === null) {
            throw PrestamoNoEncontradoException::conId($prestamoId);
        }

        $nuevaFechaFin = new DateTimeImmutable($input->nuevaFechaFin);

        $recordatoriosActuales = $this->recordatorioRepo->listarPorPrestamo($prestamo->id());
        $diasCadencia = array_map(
            fn (RecordatorioDevolucion $r): int => $r->diasAntesVencimiento(),
            $recordatoriosActuales,
        );

        $prestamo->aprobarProrroga(
            curadorId: $input->curadorId,
            nuevaFechaFin: $nuevaFechaFin,
        );

        $solicitud = $this->solicitudRepo->buscarPendientePorPrestamo($prestamo->id());
        $solicitud?->aprobar(new DateTimeImmutable);

        $nuevosRecordatorios = array_map(
            fn (int $dias): RecordatorioDevolucion => RecordatorioDevolucion::programar(
                id: $this->recordatorioRepo->nextIdentity(),
                prestamoId: $prestamo->id(),
                fechaProgramada: $nuevaFechaFin->modify("-{$dias} days"),
                diasAntesVencimiento: $dias,
            ),
            $diasCadencia,
        );

        $this->transactionManager->executeTransactional(function () use ($prestamo, $solicitud, $nuevosRecordatorios): void {
            $this->prestamoRepo->guardar($prestamo);
            if ($solicitud !== null) {
                $this->solicitudRepo->guardar($solicitud);
            }
            $this->recordatorioRepo->eliminarPorPrestamo($prestamo->id());
            $this->recordatorioRepo->guardarTodos($nuevosRecordatorios);
            foreach ($prestamo->pullEvents() as $event) {
                $this->publisher->publish($event);
            }
        });

        return AprobarProrrogaPrestamoOutput::from($prestamo);
    }
}
