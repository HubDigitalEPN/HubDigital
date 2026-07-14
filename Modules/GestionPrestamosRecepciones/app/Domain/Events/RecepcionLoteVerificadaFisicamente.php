<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Events;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoColeccion;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;

/**
 * Evento de dominio emitido cuando el lote supera la lista de verificación y la
 * recepción se aprueba: los especímenes ingresan a la colección en el estado dado.
 */
final readonly class RecepcionLoteVerificadaFisicamente
{
    public function __construct(
        public SolicitudDepositoId $solicitudId,
        public EstadoColeccion $estadoColeccion,
        public DateTimeImmutable $ocurridoEn,
    ) {}
}
