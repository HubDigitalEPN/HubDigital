<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\CompletarFirmaDigitalConIdentidad;

final readonly class CompletarFirmaDigitalConIdentidadInput
{
    public function __construct(
        public string $actaId,
        public string $investigadorId,
        public string $documentoIdentidadRuta,
    ) {}
}
