<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\BuscarEspecimenesCatalogo;

use Modules\GestionPrestamosRecepciones\Application\Ports\CatalogoEspecimenesPort;

/**
 * Busca especímenes disponibles del catálogo de inventario para que el
 * investigador los seleccione al armar una solicitud de préstamo.
 *
 * {@see BuscarEspecimenesCatalogoInput}
 * {@see BuscarEspecimenesCatalogoOutput}
 */
final class BuscarEspecimenesCatalogoHandler
{
    public function __construct(
        private readonly CatalogoEspecimenesPort $catalogo,
    ) {}

    public function handle(BuscarEspecimenesCatalogoInput $input): BuscarEspecimenesCatalogoOutput
    {
        return new BuscarEspecimenesCatalogoOutput(
            $this->catalogo->buscarDisponibles(trim($input->texto), $input->limite)
        );
    }
}
