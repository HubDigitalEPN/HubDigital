<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Events;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;

final readonly class PrestamoActivado
{
    public function __construct(
        public PrestamoId $prestamoId,
        public string $curadorId,
        public DateTimeImmutable $ocurridoEn,
    ) {}
}
