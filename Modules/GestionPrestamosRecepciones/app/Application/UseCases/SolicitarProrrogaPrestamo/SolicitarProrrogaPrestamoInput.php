<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\SolicitarProrrogaPrestamo;

final readonly class SolicitarProrrogaPrestamoInput
{
    public function __construct(
        public string $prestamoId,
        public string $investigadorId,
        public string $nuevaFechaFin,
        public string $justificacion,
    ) {}
}
