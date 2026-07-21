<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\JustificarHallazgoTaxonomico;

/**
 * Datos de entrada para justificar un hallazgo taxonómico.
 */
final readonly class JustificarHallazgoTaxonomicoInput
{
    public function __construct(
        public string $solicitudId,
        public string $matrizId,
        public string $registroId,
        public string $motivoJustificacion,
        public ?string $comentarioJustificacion = null,
    ) {}
}
