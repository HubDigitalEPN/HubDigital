<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialPrestamo;

final readonly class ConsultarHistorialPrestamoInput
{
    public function __construct(
        public string $prestamoId,
        public string $usuarioId,
    ) {}
}
