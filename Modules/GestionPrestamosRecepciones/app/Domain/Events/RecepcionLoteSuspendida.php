<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Events;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\AccionCorrectiva;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\MotivoFalloRecepcion;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;

/**
 * Evento de dominio emitido cuando el curador suspende la recepción por una anomalía
 * subsanable y se ordena una acción correctiva para el investigador. El Código QR del
 * lote permanece vigente para reintentar la recepción.
 */
final readonly class RecepcionLoteSuspendida
{
    public function __construct(
        public SolicitudDepositoId $solicitudId,
        public MotivoFalloRecepcion $motivoFallo,
        public AccionCorrectiva $accionCorrectiva,
        public DateTimeImmutable $ocurridoEn,
    ) {}
}
