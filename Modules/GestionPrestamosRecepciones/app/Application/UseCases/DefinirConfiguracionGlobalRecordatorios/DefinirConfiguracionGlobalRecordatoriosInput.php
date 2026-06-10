<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\DefinirConfiguracionGlobalRecordatorios;

/**
 * Input DTO para el caso de uso de definir la configuración global de recordatorios.
 */
final readonly class DefinirConfiguracionGlobalRecordatoriosInput
{
    /**
     * @param string $curadorId ID del curador que define la configuración.
     * @param list<int> $diasAntes Lista de días antes del vencimiento en que se deben enviar recordatorios.
     */
    public function __construct(
        public string $curadorId,
        public array $diasAntes,
    ) {}
}
