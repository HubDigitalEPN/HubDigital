<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConfirmarRevisionEspecimen;

final readonly class ConfirmarRevisionEspecimenInput
{
    public function __construct(
        public string $especimenId,
    ) {}
}
