<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Adapters;

use Illuminate\Support\Facades\DB;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\TransactionManagerPort;

/**
 * Adaptador que envuelve una operación dentro de una transacción de base de datos de
 * Laravel, garantizando atomicidad. Implementa el {@see TransactionManagerPort} para que
 * los casos de uso agrupen escrituras sin acoplarse a la fachada DB.
 */
class LaravelTransactionManagerAdapter implements TransactionManagerPort
{
    /**
     * Ejecuta el callback dentro de una transacción de Laravel y devuelve su resultado;
     * si el callback lanza una excepción, la transacción se revierte automáticamente.
     */
    public function executeTransactional(callable $callback): mixed
    {
        return DB::transaction($callback);
    }
}
