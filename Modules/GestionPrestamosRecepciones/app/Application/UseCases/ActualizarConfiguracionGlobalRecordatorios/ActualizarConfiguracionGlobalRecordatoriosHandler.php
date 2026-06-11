<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ActualizarConfiguracionGlobalRecordatorios;

use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\ConfiguracionGlobalRecordatoriosRepositoryInterface;
use RuntimeException;

/**
 * Caso de uso: Actualiza la configuración global de recordatorios para préstamos.
 * 
 * {@see ActualizarConfiguracionGlobalRecordatoriosInput}
 * {@see ActualizarConfiguracionGlobalRecordatoriosOutput}
 */
final class ActualizarConfiguracionGlobalRecordatoriosHandler
{
    /**
     * Crea una nueva instancia de ActualizarConfiguracionGlobalRecordatoriosHandler.
     * @param ConfiguracionGlobalRecordatoriosRepositoryInterface $configRepo Repositorio de configuración de recordatorios.
     * @param EventPublisherPort $publisher Puerto para la publicación de eventos.
     * @param TransactionManagerPort $transactionManager Puerto para la gestión de transacciones.
     */
    public function __construct(
        private readonly ConfiguracionGlobalRecordatoriosRepositoryInterface $configRepo,
        private readonly EventPublisherPort $publisher,
        private readonly TransactionManagerPort $transactionManager,
    ) {}

    /**
     * Ejecuta el caso de uso.
     * @param ActualizarConfiguracionGlobalRecordatoriosInput $input
     * @return ActualizarConfiguracionGlobalRecordatoriosOutput
     * @throws RuntimeException Si no existe una configuración global definida.
     */
    public function handle(ActualizarConfiguracionGlobalRecordatoriosInput $input): ActualizarConfiguracionGlobalRecordatoriosOutput
    {
        $configuracion = $this->configRepo->obtenerUnica();

        if ($configuracion === null) {
            throw new RuntimeException('No existe una configuración global de recordatorios definida.');
        }

        $configuracion->actualizar(
            curadorId: $input->curadorId,
            diasAntes: $input->diasAntes,
        );

        $this->transactionManager->executeTransactional(function () use ($configuracion): void {
            $this->configRepo->guardar($configuracion);
            foreach ($configuracion->pullEvents() as $event) {
                $this->publisher->publish($event);
            }
        });

        return ActualizarConfiguracionGlobalRecordatoriosOutput::from($configuracion);
    }
}
