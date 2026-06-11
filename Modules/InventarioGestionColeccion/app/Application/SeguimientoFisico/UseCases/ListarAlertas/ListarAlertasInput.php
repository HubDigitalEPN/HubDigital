<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarAlertas;

/**
 * DTO de entrada con el estado por el que filtrar las alertas (o null para todas).
 */
final readonly class ListarAlertasInput
{
    public function __construct(public ?string $estado = null) {}
}
