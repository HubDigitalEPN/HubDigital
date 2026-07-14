<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Events;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;

/**
 * Evento de dominio emitido cuando el curador adjunta el PDF del Acta de Recepción
 * firmado electrónicamente (FirmaEC). La ruta apunta al PDF firmado ya almacenado.
 */
final readonly class ActaRecepcionFirmada
{
    public function __construct(
        public SolicitudDepositoId $solicitudId,
        public string $ruta,
        public DateTimeImmutable $ocurridoEn,
    ) {}
}
