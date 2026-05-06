<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Events;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudPrestamoId;

final readonly class SolicitudPrestamoRegistrada
{
    public function __construct(
        public SolicitudPrestamoId $solicitudId,
        public string $investigadorId,
        public DateTimeImmutable $ocurridoEn,
    ) {}
}
