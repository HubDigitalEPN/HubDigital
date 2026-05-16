<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\Fakes;

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\TransactionManagerPort;

final class PassThroughTransactionManagerAdapter implements TransactionManagerPort
{
    public function executeTransactional(callable $callback): mixed
    {
        return $callback();
    }
}
