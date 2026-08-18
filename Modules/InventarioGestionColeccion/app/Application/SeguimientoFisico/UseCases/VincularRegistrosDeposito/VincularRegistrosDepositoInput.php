<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\VincularRegistrosDeposito;

/**
 * Entrada del backfill del vínculo fila de matriz ↔ espécimen.
 *
 * En primitivos: cruza desde GestionPrestamosRecepciones, que es quien conoce el orden
 * real de la matriz.
 *
 * @param  array<int, string>  $registroIdPorIndice  índice de matriz (1..n) => uuid del registro
 * @param  array<int, string>  $nombreCientificoPorIndice  índice => nombre declarado, para verificar
 */
final readonly class VincularRegistrosDepositoInput
{
    public function __construct(
        public string $solicitudDepositoId,
        public array $registroIdPorIndice,
        public array $nombreCientificoPorIndice = [],
        public bool $simular = false,
    ) {}
}
