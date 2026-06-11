<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Events;

use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\MatrizEspeciesId;

/**
 * Evento de dominio emitido cuando se carga la matriz de especies de un trámite desde el Excel.
 */
final class MatrizEspeciesCargada extends DomainEvent
{
    public function __construct(
        public readonly MatrizEspeciesId $matrizId,
        public readonly string $solicitudId,
        public readonly string $tipoTramite,
    ) {
        parent::__construct();
    }

    public function nombreEvento(): string
    {
        return 'matriz_especies.cargada';
    }
}
