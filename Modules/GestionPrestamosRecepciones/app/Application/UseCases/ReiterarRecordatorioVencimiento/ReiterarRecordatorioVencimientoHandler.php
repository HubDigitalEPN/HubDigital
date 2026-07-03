<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ReiterarRecordatorioVencimiento;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\PrestamoNoEncontradoException;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\PrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;

/**
 * Manejador del caso de uso para reenviar manualmente el recordatorio de vencimiento
 * de un préstamo ya vencido. Re-emite el evento de recordatorio, que el listener de
 * notificaciones traduce en el correo de préstamo vencido al investigador.
 *
 * {@see ReiterarRecordatorioVencimientoInput}
 * {@see ReiterarRecordatorioVencimientoOutput}
 */
final class ReiterarRecordatorioVencimientoHandler
{
    /**
     * @param PrestamoRepositoryInterface $prestamoRepo Repositorio de préstamos.
     * @param TransactionManagerPort $transactionManager Gestor de transacciones.
     * @param EventPublisherPort $publisher Publicador de eventos.
     */
    public function __construct(
        private readonly PrestamoRepositoryInterface $prestamoRepo,
        private readonly TransactionManagerPort $transactionManager,
        private readonly EventPublisherPort $publisher,
    ) {}

    /**
     * Ejecuta el caso de uso.
     *
     * @param ReiterarRecordatorioVencimientoInput $input Datos de entrada.
     * @return ReiterarRecordatorioVencimientoOutput Resultado del reenvío.
     * @throws PrestamoNoEncontradoException Si el préstamo no existe.
     */
    public function handle(ReiterarRecordatorioVencimientoInput $input): ReiterarRecordatorioVencimientoOutput
    {
        $prestamoId = PrestamoId::fromString($input->prestamoId);

        $prestamo = $this->prestamoRepo->buscarPorId($prestamoId);

        if ($prestamo === null) {
            throw PrestamoNoEncontradoException::conId($prestamoId);
        }

        $prestamo->reiterarVencimiento(new DateTimeImmutable);

        $this->transactionManager->executeTransactional(function () use ($prestamo): void {
            $this->prestamoRepo->guardar($prestamo);
            foreach ($prestamo->pullEvents() as $event) {
                $this->publisher->publish($event);
            }
        });

        return ReiterarRecordatorioVencimientoOutput::from(true);
    }
}
