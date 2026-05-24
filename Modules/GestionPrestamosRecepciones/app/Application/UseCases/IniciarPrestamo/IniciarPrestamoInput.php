<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\IniciarPrestamo;

final readonly class IniciarPrestamoInput
{
    public function __construct(
        public string $actaPrestamoId,
        public string $solicitudId,
    ) {}
}
