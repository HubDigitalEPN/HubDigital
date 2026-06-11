<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\DefinirConfiguracionGlobalRecordatorios;

use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Entities\ConfiguracionGlobalRecordatorios;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\ConfiguracionGlobalRecordatoriosRepositoryInterface;

/**
 * Manejador del caso de uso para definir la configuración global de recordatorios.
 * 
 * {@see DefinirConfiguracionGlobalRecordatoriosInput}
 * {@see DefinirConfiguracionGlobalRecordatoriosOutput}
 */
final class DefinirConfiguracionGlobalRecordatoriosHandler
{
    /**
     * @param ConfiguracionGlobalRecordatoriosRepositoryInterface $configRepo Repositorio de configuración global de recordatorios.
     * @param EventPublisherPort $publisher Publicador de eventos.
     * @param TransactionManagerPort $transactionManager Gestor de transacciones.
     */
    public function __construct(
        private readonly ConfiguracionGlobalRecordatoriosRepositoryInterface $configRepo,
        private readonly EventPublisherPort $publisher,
        private readonly TransactionManagerPort $transactionManager,
    ) {}

    /**
     * Ejecuta el caso de uso.
     *
     * @param DefinirConfiguracionGlobalRecordatoriosInput $input Datos de entrada.
     * @return DefinirConfiguracionGlobalRecordatoriosOutput Datos de la configuración definida.
     */
    public function handle(DefinirConfiguracionGlobalRecordatoriosInput $input): DefinirConfiguracionGlobalRecordatoriosOutput
    {
        $configuracion = ConfiguracionGlobalRecordatorios::definir(
            id: $this->configRepo->nextIdentity(),
            curadorId: $input->curadorId,
            diasAntes: $input->diasAntes,
        );

        $this->transactionManager->executeTransactional(function () use ($configuracion): void {
            $this->configRepo->guardar($configuracion);
            foreach ($configuracion->pullEvents() as $event) {
                $this->publisher->publish($event);
            }
        });

        return DefinirConfiguracionGlobalRecordatoriosOutput::from($configuracion);
    }
}
