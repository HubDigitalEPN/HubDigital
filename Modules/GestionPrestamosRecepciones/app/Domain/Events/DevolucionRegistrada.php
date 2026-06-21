<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Events;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;

/**
 * Evento de dominio emitido cuando el investigador notifica el envío de devolución de los especímenes.
 */
final readonly class DevolucionRegistrada
{
    public function __construct(
        public PrestamoId $prestamoId,
        public string $investigadorId,
        public DateTimeImmutable $ocurridoEn,
    ) {}
}
