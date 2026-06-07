<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\LocalizarEspecimen;

final readonly class LocalizarEspecimenInput
{
    public function __construct(
        public string $especimenId,
    ) {}
}
