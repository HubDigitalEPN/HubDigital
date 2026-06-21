<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Adapters;

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\ContextoEjecucionPort;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\ActorRol;

/**
 * Adaptador que describe el contexto de ejecución para las peticiones entrantes del ESP32,
 * identificando al actor como el dispositivo IoT. Implementa el {@see ContextoEjecucionPort}
 * para que los casos de uso sepan quién origina el evento sin acoplarse al transporte HTTP.
 */
class HttpSeguridadContextoAdapter implements ContextoEjecucionPort
{
    /**
     * Devuelve el rol del actor, siempre el dispositivo ESP32, ya que este adaptador atiende
     * exclusivamente el barrido del hardware de seguimiento físico.
     */
    public function actorRol(): ActorRol
    {
        return ActorRol::from('esp32');
    }

    /**
     * Devuelve el identificador del actor; es null porque el ESP32 no se modela como un
     * usuario individual del sistema.
     */
    public function actorId(): ?string
    {
        return null;
    }
}
