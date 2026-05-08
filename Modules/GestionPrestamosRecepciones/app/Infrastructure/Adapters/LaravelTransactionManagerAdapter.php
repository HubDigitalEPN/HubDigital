<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Adapters;

use Illuminate\Support\Facades\DB;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;

final class LaravelTransactionManagerAdapter implements TransactionManagerPort
{
    public function executeTransactional(callable $operation): void
    {
        DB::transaction($operation);
    }
}
