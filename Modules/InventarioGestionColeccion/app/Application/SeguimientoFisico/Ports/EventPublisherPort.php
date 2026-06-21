<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports;

/**
 * Publica los eventos de dominio del componente hacia el bus de eventos de la aplicación,
 * desacoplando los handlers del mecanismo concreto de despacho (eventos de Laravel).
 */
interface EventPublisherPort
{
    /** Despacha el evento de dominio indicado hacia sus oyentes. */
    public function publish(object $evento): void;
}
