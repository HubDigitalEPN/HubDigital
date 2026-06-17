<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\DefinirConfiguracionGlobalRecordatorios;

use Modules\GestionPrestamosRecepciones\Domain\Entities\ConfiguracionGlobalRecordatorios;

/**
 * Output DTO para el caso de uso de definir la configuración global de recordatorios.
 */
final readonly class DefinirConfiguracionGlobalRecordatoriosOutput
{
    /**
     * @param string $configuracionId ID de la configuración.
     * @param list<int> $diasAntes Lista de días antes para recordatorios.
     */
    public function __construct(
        public string $configuracionId,
        public array $diasAntes,
    ) {}

    /**
     * Crea una instancia de salida a partir de una entidad ConfiguracionGlobalRecordatorios.
     *
     * @param ConfiguracionGlobalRecordatorios $configuracion Entidad de configuración.
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
