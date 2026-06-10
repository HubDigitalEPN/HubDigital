<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Adapters;

use Illuminate\Support\Facades\DB;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;

/**
 * Adaptador para gestionar transacciones utilizando el Transaction Manager de Laravel.
 *
 * Implementa {@see TransactionManagerPort} delegando la ejecución a `DB::transaction()`.
 */
final class LaravelTransactionManagerAdapter implements TransactionManagerPort
{
    /**
     * Ejecuta una operación dentro de una transacción de base de datos.
     */
    public function executeTransactional(callable $operation): void
    {
        DB::transaction($operation);
    }
}
