<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\RevertirSugerenciaTaxonomica;

use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoMatrizEspecies;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoRegistroEspecimen;

/**
 * Datos de salida tras revertir una sugerencia taxonómica.
 */
final readonly class RevertirSugerenciaTaxonomicaOutput
{
    public function __construct(
        public string $registroId,
        public EstadoRegistroEspecimen $estadoRegistro,
        public EstadoMatrizEspecies $estadoMatriz,
        public string $especieOriginal,
    ) {}
}
