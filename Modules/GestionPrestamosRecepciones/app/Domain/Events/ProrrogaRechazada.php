<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Events;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;

/**
 * Evento de dominio emitido cuando el curador rechaza una solicitud de prórroga;
 * el préstamo vuelve a estado activo conservando su fecha de fin original.
 */
final readonly class ProrrogaRechazada
{
    public function __construct(
        public PrestamoId $prestamoId,
        public string $investigadorId,
        public string $curadorId,
        public string $comentario,
        public DateTimeImmutable $ocurridoEn,
    ) {}
}
