<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\Ports;

interface TransactionManagerPort
{
    public function executeTransactional(callable $callback): mixed;
}
