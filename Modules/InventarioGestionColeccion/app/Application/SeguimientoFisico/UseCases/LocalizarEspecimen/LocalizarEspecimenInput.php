<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\LocalizarEspecimen;

/**
 * DTO de entrada con el espécimen que se quiere localizar en el mapa.
 */
final readonly class LocalizarEspecimenInput
{
    public function __construct(
        public string $especimenId,
    ) {}
}
