<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\Ports;

interface TransactionManagerPort
{
    public function executeTransactional(callable $operation): mixed;
}
