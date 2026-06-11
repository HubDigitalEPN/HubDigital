<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ResolverAlerta;

/**
 * DTO de salida con el identificador de la alerta y su estado tras ser resuelta.
 */
final readonly class ResolverAlertaOutput
{
    public function __construct(
        public string $alertaId,
        public string $estado,
    ) {}
}
