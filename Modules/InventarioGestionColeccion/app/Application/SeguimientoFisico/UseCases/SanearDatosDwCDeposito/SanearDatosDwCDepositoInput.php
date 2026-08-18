<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\SanearDatosDwCDeposito;

/**
 * @param  array<int, array<string, mixed>>  $filasPorIndice  índice de matriz => registro DwC
 */
final readonly class SanearDatosDwCDepositoInput
{
    public function __construct(
        public string $solicitudDepositoId,
        public array $filasPorIndice,
        public bool $simular = true,
        /** Depositante del lote; si viaja, se resuelve su entidad y se rellena donde falte. */
        public ?string $depositanteNombre = null,
        public ?string $depositanteInstitucion = null,
        public ?string $depositanteEmail = null,
    ) {}
}
