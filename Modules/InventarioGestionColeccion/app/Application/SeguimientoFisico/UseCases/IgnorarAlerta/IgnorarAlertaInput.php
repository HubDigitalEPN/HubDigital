<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\IgnorarAlerta;

final readonly class IgnorarAlertaInput
{
    public function __construct(public string $alertaId) {}
}
