<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\DeclararSinDocumentacion;

final readonly class DeclararSinDocumentacionInput
{
    public function __construct(
        public string $solicitudId,
    ) {}
}
