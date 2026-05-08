<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Infrastructure\Adapters;

use Illuminate\Support\Facades\DB;
use Modules\CatalogoPublico\Application\Ports\TransactionManagerPort;

final class LaravelTransactionManager implements TransactionManagerPort
{
    public function executeTransactional(callable $operation): mixed
    {
        return DB::transaction($operation);
    }
}
