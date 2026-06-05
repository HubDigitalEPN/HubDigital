<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\CambiarJustificacionTaxonomica;

final readonly class CambiarJustificacionTaxonomicaOutput
{
    public function __construct(
        public string $registroId,
        public string $nuevoMotivo,
    ) {}
}
