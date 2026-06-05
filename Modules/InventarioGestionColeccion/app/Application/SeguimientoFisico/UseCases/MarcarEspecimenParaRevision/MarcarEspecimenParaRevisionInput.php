<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\MarcarEspecimenParaRevision;

final readonly class MarcarEspecimenParaRevisionInput
{
    public function __construct(
        public string $especimenId,
        public string $motivo,
    ) {}
}
