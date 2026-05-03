<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\DeterminarDocumentacionRequerida;

final readonly class DeterminarDocumentacionRequeridaInput
{
    public function __construct(
        public string $solicitudId,
    ) {}
}
