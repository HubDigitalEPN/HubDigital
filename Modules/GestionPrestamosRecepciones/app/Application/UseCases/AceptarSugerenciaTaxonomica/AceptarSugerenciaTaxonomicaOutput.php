<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\AceptarSugerenciaTaxonomica;

use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoMatrizEspecies;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoRegistroEspecimen;

/**
 * Output DTO para aceptar una sugerencia taxonómica.
 */
final readonly class AceptarSugerenciaTaxonomicaOutput
{
    /**
     * Crea una nueva instancia de AceptarSugerenciaTaxonomicaOutput.
     * @param EstadoRegistroEspecimen $estadoRegistro Estado del registro del espécimen tras la corrección.
     * @param EstadoMatrizEspecies $estadoMatriz Estado actual de la matriz de especies.
     * @param string $especieOriginal Nombre científico original del espécimen.
     * @param string $especieCorregida Nombre científico de la especie aceptada.
     */
    public function __construct(
        public EstadoRegistroEspecimen $estadoRegistro,
        public EstadoMatrizEspecies $estadoMatriz,
        public string $especieOriginal,
        public string $especieCorregida,
    ) {}
}
