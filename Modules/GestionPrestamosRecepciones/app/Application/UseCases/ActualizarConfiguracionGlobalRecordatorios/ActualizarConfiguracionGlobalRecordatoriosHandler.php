<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ActualizarConfiguracionGlobalRecordatorios;

use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\ConfiguracionGlobalRecordatoriosRepositoryInterface;
use RuntimeException;

final class ActualizarConfiguracionGlobalRecordatoriosHandler
{
    public function __construct(
        private readonly ConfiguracionGlobalRecordatoriosRepositoryInterface $configRepo,
        private readonly EventPublisherPort $publisher,
        private readonly TransactionManagerPort $transactionManager,
    ) {}

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
