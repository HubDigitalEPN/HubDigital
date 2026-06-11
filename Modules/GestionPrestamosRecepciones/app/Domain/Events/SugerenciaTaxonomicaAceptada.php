<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Events;

use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\MatrizEspeciesId;

/**
 * Evento de dominio emitido cuando se acepta una sugerencia de corrección taxonómica en un registro de la matriz.
 */
final class SugerenciaTaxonomicaAceptada extends DomainEvent
{
    public function __construct(
        public readonly MatrizEspeciesId $matrizId,
        public readonly string $registroId,
        public readonly string $especieOriginal,
        public readonly string $especieCorregida,
    ) {
        parent::__construct();
    }

    public function nombreEvento(): string
    {
        return 'matriz_especies.sugerencia_taxonomica_aceptada';
    }
}
