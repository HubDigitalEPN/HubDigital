<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Events;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;

/**
 * Evento de dominio emitido cuando el investigador solicita una prórroga de un
 * préstamo activo, dejándolo a la espera de la resolución del curador.
 */
final readonly class ProrrogaSolicitada
{
    public function __construct(
        public PrestamoId $prestamoId,
        public string $investigadorId,
        public DateTimeImmutable $ocurridoEn,
    ) {}
}
