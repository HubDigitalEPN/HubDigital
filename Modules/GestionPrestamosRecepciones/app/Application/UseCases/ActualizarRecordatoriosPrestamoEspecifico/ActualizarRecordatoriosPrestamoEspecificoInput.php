<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ActualizarRecordatoriosPrestamoEspecifico;

/**
 * Input DTO para actualizar los recordatorios de un préstamo específico.
 */
final readonly class ActualizarRecordatoriosPrestamoEspecificoInput
{
    /**
     * Crea una nueva instancia de ActualizarRecordatoriosPrestamoEspecificoInput.
     * @param string $prestamoId ID del préstamo.
     * @param string $curadorId ID del curador que realiza la actualización.
     * @param list<int> $diasAntes Lista de días de antelación para enviar recordatorios.
     */
    public function __construct(
        public string $prestamoId,
        public string $curadorId,
        public array $diasAntes,
    ) {}
}
