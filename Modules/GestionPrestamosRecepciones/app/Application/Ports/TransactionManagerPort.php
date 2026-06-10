<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\Ports;

/**
 * Puerto de la capa de aplicación para ejecutar operaciones dentro de una transacción
 * de base de datos.
 *
 * Lo implementa un adaptador en Infrastructure sobre la transacción de Laravel. Los
 * Handlers lo usan para guardar el agregado y publicar sus eventos de forma atómica.
 */
interface TransactionManagerPort
{
    /** Ejecuta el callback dentro de una transacción; revierte si lanza una excepción. */
    public function executeTransactional(callable $operation): void;
}
