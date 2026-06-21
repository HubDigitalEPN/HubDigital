<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Horario;

/**
 * Contrato de persistencia para el {@see Horario} laboral. Se modela como un
 * singleton de configuración: existe un único horario para todo el laboratorio.
 */
interface HorarioRepository
{
    /** Devuelve el horario único configurado (creándolo con valores por defecto si hiciera falta). */
    public function obtenerUnico(): Horario;

    /** Persiste los cambios del horario. */
    public function guardar(Horario $horario): void;
}
