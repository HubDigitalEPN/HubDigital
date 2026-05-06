<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Events;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudPrestamoId;

final readonly class SolicitudPrestamoRechazada
{
    public function __construct(
        public SolicitudPrestamoId $solicitudId,
        public string $curadorId,
        public string $motivo,
        public DateTimeImmutable $ocurridoEn,
    ) {}
}
