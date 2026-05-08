<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\DeclararSinDocumentacion;

final readonly class DeclararSinDocumentacionOutput
{
    public function __construct(
        public bool $sinDocumentacion,
    ) {}
}
