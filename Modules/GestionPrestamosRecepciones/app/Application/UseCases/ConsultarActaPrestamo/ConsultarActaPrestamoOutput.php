<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarActaPrestamo;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\Entities\ActaPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoActa;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoPrestamo;

/**
 * Output DTO para el caso de uso de consultar un acta de préstamo.
 */
final readonly class ConsultarActaPrestamoOutput
{
    /**
     * @param string $actaId ID del acta de préstamo.
     * @param string $numeroPrestamo Número del préstamo.
     * @param string $solicitudPrestamoId ID de la solicitud de préstamo asociada.
     * @param EstadoActa $estado Estado actual del acta.
     * @param TipoPrestamo $tipoPrestamo Tipo de préstamo.
     * @param DateTimeImmutable $fechaInicio Fecha de inicio del préstamo.
     * @param DateTimeImmutable $fechaFin Fecha de fin del préstamo.
     */
    public function __construct(
        public string $actaId,
        public string $numeroPrestamo,
        public string $solicitudPrestamoId,
        public EstadoActa $estado,
        public TipoPrestamo $tipoPrestamo,
        public DateTimeImmutable $fechaInicio,
        public DateTimeImmutable $fechaFin,
    ) {}

    /**
     * Crea una instancia de salida a partir de la entidad ActaPrestamo.
     *
     * @param ActaPrestamo $acta Entidad ActaPrestamo.
     * @return self
     */
    public static function fromEntity(ActaPrestamo $acta): self
    {
        return new self(
            actaId: (string) $acta->id(),
            numeroPrestamo: (string) $acta->codigoPrestamo(),
            solicitudPrestamoId: (string) $acta->solicitudPrestamoId(),
            estado: $acta->estado(),
            tipoPrestamo: $acta->tipoPrestamo(),
            fechaInicio: $acta->fechaInicio(),
            fechaFin: $acta->fechaFin(),
        );
    }
}
