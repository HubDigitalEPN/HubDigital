<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Events;

use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\MatrizEspeciesId;

final class JustificacionTaxonomicaCambiada extends DomainEvent
{
    public function __construct(
        public readonly MatrizEspeciesId $matrizId,
        public readonly string $registroId,
        public readonly string $especie,
        public readonly string $nuevoMotivo,
    ) {
        parent::__construct();
    }

    public function nombreEvento(): string
    {
        return 'matriz_especies.justificacion_taxonomica_cambiada';
    }
}
