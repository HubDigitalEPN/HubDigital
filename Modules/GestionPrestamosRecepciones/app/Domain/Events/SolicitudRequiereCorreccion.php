<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Events;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;

/**
 * Evento de dominio emitido cuando una solicitud pasa a requerir corrección, ya sea
 * porque el curador no aceptó alguna justificación o por un rechazo subsanable.
 */
final readonly class SolicitudRequiereCorreccion
{
    public function __construct(
        public SolicitudDepositoId $solicitudId,
        public string $curadorId,
        public string $comentario,
        public DateTimeImmutable $ocurridoEn,
    ) {}
}
