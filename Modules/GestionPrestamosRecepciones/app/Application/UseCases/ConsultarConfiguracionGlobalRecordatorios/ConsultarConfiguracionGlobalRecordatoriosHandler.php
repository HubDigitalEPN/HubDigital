<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarConfiguracionGlobalRecordatorios;

use Modules\GestionPrestamosRecepciones\Domain\Repositories\ConfiguracionGlobalRecordatoriosRepositoryInterface;

/**
 * Manejador del caso de uso para consultar la configuración global de recordatorios.
 *
 * {@see ConsultarConfiguracionGlobalRecordatoriosInput}
 * {@see ConsultarConfiguracionGlobalRecordatoriosOutput}
 */
final class ConsultarConfiguracionGlobalRecordatoriosHandler
{
    public function __construct(
        private readonly ConfiguracionGlobalRecordatoriosRepositoryInterface $configRepo,
    ) {}

    /**
     * Ejecuta el caso de uso.
     */
    public function handle(ConsultarConfiguracionGlobalRecordatoriosInput $input): ConsultarConfiguracionGlobalRecordatoriosOutput
    {
        $configuracion = $this->configRepo->obtenerUnica();

        if ($configuracion === null) {
            return new ConsultarConfiguracionGlobalRecordatoriosOutput(
                configuracionId: null,
                diasAntes: null,
            );
        }

        return new ConsultarConfiguracionGlobalRecordatoriosOutput(
            configuracionId: (string) $configuracion->id(),
            diasAntes: $configuracion->diasAntes(),
        );
    }
}
