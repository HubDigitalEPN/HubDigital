<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ActualizarRecordatoriosPrestamoEspecifico;

use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;

/**
 * Output DTO para la actualización de recordatorios de un préstamo específico.
 */
final readonly class ActualizarRecordatoriosPrestamoEspecificoOutput
{
    /**
     * Crea una nueva instancia de ActualizarRecordatoriosPrestamoEspecificoOutput.
     * @param string $prestamoId ID del préstamo.
     * @param int $cantidadRecordatorios Número de recordatorios programados.
     */
    public function __construct(
        public string $prestamoId,
        public int $cantidadRecordatorios,
    ) {}

    /**
     * Crea una instancia a partir del ID del préstamo y la cantidad de recordatorios.
     * @param PrestamoId $prestamoId
     * @param int $cantidadRecordatorios
     * @return self
     */
    public static function from(PrestamoId $prestamoId, int $cantidadRecordatorios): self
    {
        return new self(
            prestamoId: (string) $prestamoId,
            cantidadRecordatorios: $cantidadRecordatorios,
        );
    }
}
