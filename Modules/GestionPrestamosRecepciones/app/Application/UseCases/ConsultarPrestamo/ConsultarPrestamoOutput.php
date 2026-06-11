<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarPrestamo;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoPrestamo;

/**
 * Output DTO para el caso de uso de consultar un préstamo.
 */
final readonly class ConsultarPrestamoOutput
{
    /**
     * @param string $prestamoId ID del préstamo.
     * @param string $actaPrestamoId ID del acta de préstamo.
     * @param string $investigadorId ID del investigador.
     * @param EstadoPrestamo $estado Estado actual del préstamo.
     * @param DateTimeImmutable $iniciadoEn Fecha de inicio.
     * @param DateTimeImmutable $fechaFin Fecha de finalización.
     * @param string $numeroPrestamo Número de préstamo asignado.
     */
    public function __construct(
        public string $prestamoId,
        public string $actaPrestamoId,
        public string $investigadorId,
        public EstadoPrestamo $estado,
        public DateTimeImmutable $iniciadoEn,
        public DateTimeImmutable $fechaFin,
        public string $numeroPrestamo,
    ) {}
}
