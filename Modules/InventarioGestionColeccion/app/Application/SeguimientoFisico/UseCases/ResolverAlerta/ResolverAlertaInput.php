<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ResolverAlerta;

/**
 * DTO de entrada con la alerta a resolver y el motivo de su resolución.
 */
final readonly class ResolverAlertaInput
{
    public function __construct(
        public string $alertaId,
        public string $motivoResolucion,
    ) {}
}
