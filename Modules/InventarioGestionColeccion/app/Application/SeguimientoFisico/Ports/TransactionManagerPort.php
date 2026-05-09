<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports;

interface TransactionManagerPort
{
    public function executeTransactional(callable $callback): mixed;
}
