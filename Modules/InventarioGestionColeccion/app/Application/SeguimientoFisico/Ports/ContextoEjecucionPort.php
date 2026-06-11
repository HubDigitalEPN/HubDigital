<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\ActorRol;

/**
 * Expone el contexto del actor que ejecuta el caso de uso (rol y, opcionalmente, su
 * identificador). Permite a los handlers tomar decisiones según quién dispara la acción
 * sin acoplarse al sistema de autenticación de Laravel.
 */
interface ContextoEjecucionPort
{
    /** Devuelve el rol del actor que ejecuta la acción actual. */
    public function actorRol(): ActorRol;

    /** Devuelve el identificador del actor, o null cuando la acción no tiene un usuario asociado. */
    public function actorId(): ?string;
}
