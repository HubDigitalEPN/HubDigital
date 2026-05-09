<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Horario;

interface HorarioRepository
{
    public function obtenerUnico(): Horario;

    public function guardar(Horario $horario): void;
}
