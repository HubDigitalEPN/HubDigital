<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarActaPrestamo;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\Entities\ActaPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoActa;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoPrestamo;

final readonly class ConsultarActaPrestamoOutput
{
    public function __construct(
        public string $actaId,
        public string $numeroPrestamo,
        public string $solicitudPrestamoId,
        public EstadoActa $estado,
        public TipoPrestamo $tipoPrestamo,
        public DateTimeImmutable $fechaInicio,
        public DateTimeImmutable $fechaFin,
    ) {}

    public static function fromEntity(ActaPrestamo $acta): self
    {
        return new self(
            actaId: (string) $acta->id(),
            numeroPrestamo: (string) $acta->numeroPrestamo(),
            solicitudPrestamoId: (string) $acta->solicitudPrestamoId(),
            estado: $acta->estado(),
            tipoPrestamo: $acta->tipoPrestamo(),
            fechaInicio: $acta->fechaInicio(),
            fechaFin: $acta->fechaFin(),
        );
    }
}
