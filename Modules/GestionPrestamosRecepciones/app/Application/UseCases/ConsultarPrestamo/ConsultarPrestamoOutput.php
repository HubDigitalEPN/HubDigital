<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarPrestamo;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\Entities\Prestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoPrestamo;

final readonly class ConsultarPrestamoOutput
{
    public function __construct(
        public string $prestamoId,
        public string $actaPrestamoId,
        public string $investigadorId,
        public EstadoPrestamo $estado,
        public DateTimeImmutable $iniciadoEn,
        public DateTimeImmutable $fechaFin,
    ) {}

    public static function fromEntity(Prestamo $prestamo): self
    {
        return new self(
            prestamoId: (string) $prestamo->id(),
            actaPrestamoId: (string) $prestamo->actaPrestamoId(),
            investigadorId: $prestamo->investigadorId(),
            estado: $prestamo->estado(),
            iniciadoEn: $prestamo->iniciadoEn(),
            fechaFin: $prestamo->fechaFin(),
        );
    }
}
