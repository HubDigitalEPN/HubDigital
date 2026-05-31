<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\CambiarJustificacionTaxonomica;

final readonly class CambiarJustificacionTaxonomicaInput
{
    public function __construct(
        public string $solicitudId,
        public string $matrizId,
        public string $registroId,
        public string $nuevoMotivo,
    ) {}
}
