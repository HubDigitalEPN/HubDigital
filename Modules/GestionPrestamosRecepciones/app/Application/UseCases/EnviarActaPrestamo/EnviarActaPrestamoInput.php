<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\EnviarActaPrestamo;

final readonly class EnviarActaPrestamoInput
{
    public function __construct(
        public string $actaId,
        public string $curadorId,
    ) {}
}
