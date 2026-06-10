<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ActualizarConfiguracionGlobalRecordatorios;

use Modules\GestionPrestamosRecepciones\Domain\Entities\ConfiguracionGlobalRecordatorios;

/**
 * Output DTO para la actualización de la configuración global de recordatorios.
 */
final readonly class ActualizarConfiguracionGlobalRecordatoriosOutput
{
    /**
     * Crea una nueva instancia de ActualizarConfiguracionGlobalRecordatoriosOutput.
     * @param string $configuracionId ID de la configuración.
     * @param list<int> $diasAntes Lista de días de antelación para enviar recordatorios.
     */
    public function __construct(
        public string $configuracionId,
        public array $diasAntes,
    ) {}

    /**
     * Crea una instancia a partir de la entidad de configuración.
     * @param ConfiguracionGlobalRecordatorios $configuracion
     * @return self
     */
    public static function from(ConfiguracionGlobalRecordatorios $configuracion): self
    {
        return new self(
            configuracionId: (string) $configuracion->id(),
            diasAntes: $configuracion->diasAntes(),
        );
    }
}
