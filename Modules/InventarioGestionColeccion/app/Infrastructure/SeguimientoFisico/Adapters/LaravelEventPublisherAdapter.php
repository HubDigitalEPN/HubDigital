<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Adapters;

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\EventPublisherPort;

/**
 * Adaptador que publica los eventos de dominio del componente sobre el bus de eventos de
 * Laravel, permitiendo que listeners (notificaciones, alertas) reaccionen. Implementa el
 * {@see EventPublisherPort} para que la capa de aplicación dispare eventos sin conocer Laravel.
 */
class LaravelEventPublisherAdapter implements EventPublisherPort
{
    /**
     * Entrega el evento de dominio al despachador de eventos de Laravel para que sus
     * listeners registrados lo procesen.
     */
    public function publish(object $evento): void
    {
        event($evento);
    }
}
