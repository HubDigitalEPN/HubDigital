<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\BuscarEspecimenesCatalogo;

use Modules\GestionPrestamosRecepciones\Application\Ports\EspecimenCatalogoDto;

/**
 * Resultado de la búsqueda de especímenes en el catálogo.
 */
final readonly class BuscarEspecimenesCatalogoOutput
{
    /**
     * @param  list<EspecimenCatalogoDto>  $resultados
     */
    public function __construct(
        public array $resultados,
    ) {}
}
