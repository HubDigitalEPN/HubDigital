<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarProrrogaPrestamo;

final readonly class AprobarProrrogaPrestamoInput
{
    public function __construct(
        public string $prestamoId,
        public string $curadorId,
        public string $nuevaFechaFin,
    ) {}
}
