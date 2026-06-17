<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Events;

use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\MatrizEspeciesId;

/**
 * Evento de dominio emitido cuando se justifica un hallazgo taxonómico no catalogado en la matriz de especies.
 */
final class HallazgoTaxonomicoJustificado extends DomainEvent
{
    public function __construct(
        public readonly MatrizEspeciesId $matrizId,
        public readonly string $registroId,
        public readonly string $especie,
        public readonly string $motivoJustificacion,
    ) {
        parent::__construct();
    }

    public function nombreEvento(): string
    {
        return 'matriz_especies.hallazgo_taxonomico_justificado';
    }
}
