<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Events;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ActaPrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudPrestamoId;

/**
 * Evento de dominio emitido cuando se envía el acta al investigador para su firma.
 */
final readonly class ActaEnviada
{
    public function __construct(
        public ActaPrestamoId $actaId,
        public SolicitudPrestamoId $solicitudId,
        public string $investigadorId,
        public DateTimeImmutable $ocurridoEn,
    ) {}
}
