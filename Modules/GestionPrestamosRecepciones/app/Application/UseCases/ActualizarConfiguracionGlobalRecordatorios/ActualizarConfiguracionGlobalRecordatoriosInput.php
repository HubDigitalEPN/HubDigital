<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ActualizarConfiguracionGlobalRecordatorios;

/**
 * Input DTO para actualizar la configuración global de recordatorios.
 */
final readonly class ActualizarConfiguracionGlobalRecordatoriosInput
{
    /**
     * Crea una nueva instancia de ActualizarConfiguracionGlobalRecordatoriosInput.
     * @param string $configuracionId ID de la configuración a actualizar.
     * @param string $curadorId ID del curador que realiza la actualización.
     * @param list<int> $diasAntes Lista de días de antelación para enviar recordatorios.
     */
    public function __construct(
        public string $configuracionId,
        public string $curadorId,
        public array $diasAntes,
    ) {}
}
