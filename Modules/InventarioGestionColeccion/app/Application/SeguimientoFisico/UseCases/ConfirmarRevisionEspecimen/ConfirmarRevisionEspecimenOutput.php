<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConfirmarRevisionEspecimen;

final readonly class ConfirmarRevisionEspecimenOutput
{
    public function __construct(
        public string $especimenId,
        public string $estadoRevision,
    ) {}
}
