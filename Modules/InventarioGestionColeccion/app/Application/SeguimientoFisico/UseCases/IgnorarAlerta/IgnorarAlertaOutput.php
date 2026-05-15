<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\IgnorarAlerta;

final readonly class IgnorarAlertaOutput
{
    public function __construct(
        public string $alertaId,
        public string $estado,
    ) {}
}
