<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\CargarMatrizEspecies;

use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoMatrizEspecies;

final readonly class CargarMatrizEspeciesOutput
{
    public function __construct(
        public string $matrizId,
        public EstadoMatrizEspecies $estadoMatriz,
        public bool $validacionTipograficaAplicada,
        public int $totalRegistros,
    ) {}
}
