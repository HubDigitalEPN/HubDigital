<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarPrestamo;

final readonly class ConsultarPrestamoInput
{
    public function __construct(
        public string $prestamoId,
        public string $usuarioId,
    ) {}
}
