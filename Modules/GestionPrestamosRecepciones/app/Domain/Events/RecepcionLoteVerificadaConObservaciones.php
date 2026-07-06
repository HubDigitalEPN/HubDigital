<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Events;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoColeccion;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;

/**
 * Evento de dominio emitido cuando el curador acepta la recepción con observaciones
 * (derivadas de los ítems no conformes del checklist): las observaciones quedan
 * registradas y los especímenes ingresan a la colección en el estado dado.
 */
final readonly class RecepcionLoteVerificadaConObservaciones
{
    public function __construct(
        public SolicitudDepositoId $solicitudId,
        public EstadoColeccion $estadoColeccion,
        public DateTimeImmutable $ocurridoEn,
    ) {}
}
