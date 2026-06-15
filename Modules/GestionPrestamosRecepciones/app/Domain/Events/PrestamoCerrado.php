<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Events;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\CondicionEspecimen;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ResultadoVerificacionDevolucion;

/**
 * Evento de dominio emitido cuando el curador cierra formalmente el préstamo
 * tras verificar la devolución de los especímenes.
 */
final readonly class PrestamoCerrado
{
    public function __construct(
        public PrestamoId $prestamoId,
        public string $investigadorId,
        public string $curadorId,
        public ResultadoVerificacionDevolucion $resultado,
        public CondicionEspecimen $condicionEspecimen,
        public DateTimeImmutable $ocurridoEn,
        public ?string $observacion = null,
    ) {}
}
