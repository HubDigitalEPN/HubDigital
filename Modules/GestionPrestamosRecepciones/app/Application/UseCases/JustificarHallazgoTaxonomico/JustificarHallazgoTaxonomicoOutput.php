<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\JustificarHallazgoTaxonomico;

use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoMatrizEspecies;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoRegistroEspecimen;

/**
 * Datos de salida tras justificar un hallazgo taxonómico.
 */
final readonly class JustificarHallazgoTaxonomicoOutput
{
    public function __construct(
        public EstadoRegistroEspecimen $estadoRegistro,
        public EstadoMatrizEspecies $estadoMatriz,
        public string $registroId,
    ) {}
}
