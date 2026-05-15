<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Adapters;

use Illuminate\Support\Facades\DB;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\TransactionManagerPort;

class LaravelTransactionManagerAdapter implements TransactionManagerPort
{
    public function executeTransactional(callable $callback): mixed
    {
        return DB::transaction($callback);
    }
}
