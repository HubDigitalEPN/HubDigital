<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Events;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;

/**
 * Evento de dominio emitido cuando el curador habilita el envío de un préstamo internacional tras recibir el documento del ministerio.
 */
final readonly class PrestamoHabilitadoParaEnvio
{
    public function __construct(
        public PrestamoId $prestamoId,
        public string $curadorId,
        public DateTimeImmutable $ocurridoEn,
    ) {}
}
