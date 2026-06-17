<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\EvaluarPlazoDevolucionPrestamo;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\PrestamoNoEncontradoException;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\ConfiguracionGlobalRecordatoriosRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\PrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoRecordatorio;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;

/**
 * Manejador del caso de uso para evaluar el plazo de devolución de un préstamo y generar recordatorios.
 * 
 * {@see EvaluarPlazoDevolucionPrestamoInput}
 * {@see EvaluarPlazoDevolucionPrestamoOutput}
 */
final class EvaluarPlazoDevolucionPrestamoHandler
{
    /**
     * @param PrestamoRepositoryInterface $prestamoRepo Repositorio de préstamos.
     * @param ConfiguracionGlobalRecordatoriosRepositoryInterface $configRepo Repositorio de configuración de recordatorios.
     * @param TransactionManagerPort $transactionManager Gestor de transacciones.
     * @param EventPublisherPort $publisher Publicador de eventos.
     */
    public function __construct(
        private readonly PrestamoRepositoryInterface $prestamoRepo,
        private readonly ConfiguracionGlobalRecordatoriosRepositoryInterface $configRepo,
        private readonly TransactionManagerPort $transactionManager,
        private readonly EventPublisherPort $publisher,
    ) {}

    /**
     * Ejecuta el caso de uso.
     *
     * @param EvaluarPlazoDevolucionPrestamoInput $input Datos de entrada.
     * @return EvaluarPlazoDevolucionPrestamoOutput Resultado de la evaluación.
     * @throws PrestamoNoEncontradoException Si el préstamo no existe.
     */
    public function handle(EvaluarPlazoDevolucionPrestamoInput $input): EvaluarPlazoDevolucionPrestamoOutput
    {
        $prestamoId = PrestamoId::fromString($input->prestamoId);

        $prestamo = $this->prestamoRepo->buscarPorId($prestamoId);

        if ($prestamo === null) {
            throw PrestamoNoEncontradoException::conId($prestamoId);
        }

        $config = $this->configRepo->obtenerUnica();

        if ($config === null) {
            return EvaluarPlazoDevolucionPrestamoOutput::from(false, null);
        }

        $ahora = new DateTimeImmutable;

        $estadoRecordatorio = $prestamo->evaluarEstadoRecordatorio(
            diasAntes: $config->diasAntes(),
            ahora: $ahora,
        );

        if ($estadoRecordatorio === null) {
            return EvaluarPlazoDevolucionPrestamoOutput::from(false, null);
        }

        if ($estadoRecordatorio === EstadoRecordatorio::Vencido) {
            $prestamo->vencer($ahora);
        } else {
            $prestamo->registrarEnvioDeRecordatorio(
                estado: $estadoRecordatorio,
                ahora: $ahora,
            );
        }

        $this->transactionManager->executeTransactional(function () use ($prestamo): void {
            $this->prestamoRepo->guardar($prestamo);
            foreach ($prestamo->pullEvents() as $event) {
                $this->publisher->publish($event);
            }
        });

        return EvaluarPlazoDevolucionPrestamoOutput::from(true, $estadoRecordatorio);
    }
}
