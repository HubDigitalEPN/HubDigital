<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\DeterminarDocumentacionRequerida;

final readonly class DeterminarDocumentacionRequeridaOutput
{
    public function __construct(
        /** @var string[] */
        public array $documentosRequeridos,
    ) {}
}
