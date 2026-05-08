<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Events;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ActaPrestamoId;

final readonly class ActaDevueltaPorFirmaInvalida
{
    public function __construct(
        public ActaPrestamoId $actaId,
        public string $investigadorId,
        public string $motivo,
        public DateTimeImmutable $ocurridoEn,
    ) {}
}
