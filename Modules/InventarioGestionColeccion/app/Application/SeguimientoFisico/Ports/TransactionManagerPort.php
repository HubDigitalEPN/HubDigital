<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports;

/**
 * Envuelve la ejecución de un bloque de trabajo dentro de una transacción atómica,
 * permitiendo a los handlers garantizar consistencia sin acoplarse a la capa de
 * persistencia concreta (transacciones de la base de datos de Laravel).
 */
interface TransactionManagerPort
{
    /** Ejecuta el callback dentro de una transacción y devuelve su resultado; revierte ante una excepción. */
    public function executeTransactional(callable $callback): mixed;
}
