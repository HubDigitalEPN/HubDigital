<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\AceptarSugerenciaTaxonomica;

use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoMatrizEspecies;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoRegistroEspecimen;

final readonly class AceptarSugerenciaTaxonomicaOutput
{
    public function __construct(
        public EstadoRegistroEspecimen $estadoRegistro,
        public EstadoMatrizEspecies $estadoMatriz,
        public string $especieOriginal,
        public string $especieCorregida,
    ) {}
}
