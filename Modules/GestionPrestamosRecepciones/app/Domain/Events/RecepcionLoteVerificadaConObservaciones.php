<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Events;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoColeccion;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoObservacionRecepcion;

/**
 * Evento de dominio emitido cuando el curador acepta la recepción con observaciones
 * por una anomalía no devolvible: las observaciones quedan registradas y los
 * especímenes ingresan a la colección en el estado dado.
 */
final readonly class RecepcionLoteVerificadaConObservaciones
{
    public function __construct(
        public SolicitudDepositoId $solicitudId,
        public TipoObservacionRecepcion $tipoObservacion,
        public EstadoColeccion $estadoColeccion,
        public DateTimeImmutable $ocurridoEn,
    ) {}
}
