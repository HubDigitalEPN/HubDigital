<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ResolverAlerta;

final readonly class ResolverAlertaOutput
{
    public function __construct(
        public string $alertaId,
        public string $estado,
    ) {}
}
