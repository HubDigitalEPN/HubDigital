<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarResumenLoteDeposito;

/** Identidad del trámite cuyo material se consulta. */
final readonly class ConsultarResumenLoteDepositoInput
{
    public function __construct(
        public string $solicitudDepositoId,
    ) {}
}
