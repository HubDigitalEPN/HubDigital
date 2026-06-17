<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Events;

use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\MatrizEspeciesId;

/**
 * Evento de dominio emitido cuando todos los registros de la matriz quedan validados y esta pasa a validada técnicamente.
 */
final class MatrizValidadaTecnicamente extends DomainEvent
{
    public function __construct(
        public readonly MatrizEspeciesId $matrizId,
        public readonly string $solicitudId,
    ) {
        parent::__construct();
    }

    public function nombreEvento(): string
    {
        return 'matriz_especies.validada_tecnicamente';
    }
}
