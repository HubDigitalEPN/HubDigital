<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarActaPrestamo;

final readonly class ConsultarActaPrestamoInput
{
    public function __construct(
        public string $actaId,
        public string $usuarioId,
    ) {}
}
