<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\CompletarDatosManualmente;

final readonly class CompletarDatosManualesInput
{
    public function __construct(
        public string $solicitudId,
        public string $campo,
        public string $valor,
    ) {}
}
