<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\MarcarEspecimenParaRevision;

final readonly class MarcarEspecimenParaRevisionOutput
{
    public function __construct(
        public string $especimenId,
        public string $estadoRevision,
        public string $motivoRevision,
    ) {}
}
