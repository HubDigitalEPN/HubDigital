<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\RevertirSugerenciaTaxonomica;

/**
 * Datos de entrada para revertir una sugerencia taxonómica.
 */
final readonly class RevertirSugerenciaTaxonomicaInput
{
    public function __construct(
        public string $solicitudId,
        public string $matrizId,
        public string $registroId,
    ) {}
}
