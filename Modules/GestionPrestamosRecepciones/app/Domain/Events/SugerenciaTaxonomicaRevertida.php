<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Events;

use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\MatrizEspeciesId;

/**
 * Evento de dominio emitido cuando se revierte una corrección taxonómica previamente aceptada.
 */
final class SugerenciaTaxonomicaRevertida extends DomainEvent
{
    public function __construct(
        public readonly MatrizEspeciesId $matrizId,
        public readonly string $registroId,
        public readonly string $especieOriginal,
    ) {
        parent::__construct();
    }

    public function nombreEvento(): string
    {
        return 'matriz_especies.sugerencia_taxonomica_revertida';
    }
}
