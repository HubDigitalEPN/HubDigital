<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ReordenarFamiliasEnGabinete;

/**
 * DTO de entrada con el gabinete y la lista de movimientos (caja → ranura destino) a aplicar.
 */
final readonly class ReordenarFamiliasEnGabineteInput
{
    /**
     * @param  array<int, array{caja_id: string, ranura_destino_id: string}>  $movimientos
     */
    public function __construct(
        public string $gabineteId,
        public array $movimientos,
    ) {}
}
