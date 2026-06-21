<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarBandejaPrestamos;

use DateTimeImmutable;

/**
 * Fila de lectura de una bandeja de préstamos.
 *
 * {@see ConsultarBandejaPrestamosOutput}
 */
final readonly class FilaPrestamoBandeja
{
    public function __construct(
        public string $prestamoId,
        public ?string $numeroPrestamo,
        public ?string $investigadorId,
        public ?string $solicitanteNombre,
        public string $estado,
        public ?DateTimeImmutable $iniciadoEn,
        public ?DateTimeImmutable $fechaFin,
    ) {}
}
