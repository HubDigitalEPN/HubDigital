<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\RechazarProrrogaPrestamo;

final readonly class RechazarProrrogaPrestamoInput
{
    public function __construct(
        public string $prestamoId,
        public string $curadorId,
        public string $comentario,
    ) {}
}
