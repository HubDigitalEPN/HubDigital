<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\IgnorarAlerta;

/**
 * DTO de salida con el identificador de la alerta y su estado tras ser ignorada.
 */
final readonly class IgnorarAlertaOutput
{
    public function __construct(
        public string $alertaId,
        public string $estado,
    ) {}
}
