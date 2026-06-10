<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Events;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;

/**
 * Evento de dominio emitido cuando el curador aprueba la verificación y el préstamo pasa a activo.
 */
final readonly class PrestamoActivado
{
    public function __construct(
        public PrestamoId $prestamoId,
        public string $curadorId,
        public DateTimeImmutable $ocurridoEn,
    ) {}
}
