<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Events;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;

/**
 * Evento de dominio emitido cuando el investigador registra la verificación de entrega de un préstamo.
 */
final readonly class VerificacionEntregaRegistrada
{
    public function __construct(
        public PrestamoId $prestamoId,
        public string $investigadorId,
        public DateTimeImmutable $ocurridoEn,
    ) {}
}
