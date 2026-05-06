<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Adapters;

use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;

final class PassThroughTransactionManagerAdapter implements TransactionManagerPort
{
    public function executeTransactional(callable $operation): void
    {
        $operation();
    }
}
