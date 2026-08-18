<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\MarcarHuerfanosDeposito;

/** Motivo con el que se anota el material cuyo trámite desapareció. */
final readonly class MarcarHuerfanosDepositoInput
{
    public function __construct(
        public string $motivo,
    ) {}
}
