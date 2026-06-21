<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\LocalizarEspecimenParaVisitante;

/**
 * DTO de entrada con el nombre científico que el visitante busca en el mapa.
 */
final readonly class LocalizarEspecimenParaVisitanteInput
{
    public function __construct(
        public string $nombreCientifico,
    ) {}
}
