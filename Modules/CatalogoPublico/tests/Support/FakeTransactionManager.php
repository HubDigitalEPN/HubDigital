<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Tests\Support;

use Modules\CatalogoPublico\Application\Ports\TransactionManagerPort;

/**
 * Ejecuta la operación directamente, sin DB::transaction. Permite que los
 * handlers que envuelven escrituras en una transacción corran contra los
 * repositorios InMemory sin necesidad de una base de datos real.
 */
final class FakeTransactionManager implements TransactionManagerPort
{
    public function executeTransactional(callable $operation): mixed
    {
        return $operation();
    }
}
