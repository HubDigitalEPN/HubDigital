<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\BuscarEspecimenesCatalogo;

/**
 * Datos de entrada para buscar especímenes en el catálogo de inventario.
 */
final readonly class BuscarEspecimenesCatalogoInput
{
    public function __construct(
        public string $texto,
        public int $limite = 15,
    ) {}
}
