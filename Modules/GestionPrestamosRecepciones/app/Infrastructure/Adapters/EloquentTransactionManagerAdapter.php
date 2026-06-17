<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Adapters;

use Illuminate\Support\Facades\DB;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;

/**
 * Adaptador para gestionar transacciones de base de datos utilizando la fachada DB de Laravel.
 *
 * Implementa {@see TransactionManagerPort}.
 */
final class EloquentTransactionManagerAdapter implements TransactionManagerPort
{
    /**
     * Ejecuta una función de retorno dentro de una transacción de base de datos.
     *
     * @param  callable  $callback  La operación a ejecutar transaccionalmente.
     * @return mixed El resultado de la función de retorno.
     *
     * @throws \Throwable
     */
    public function executeTransactional(callable $callback): mixed
    {
        return DB::transaction($callback);
    }
}
