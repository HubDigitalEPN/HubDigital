<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\Repositories;

use Modules\InventarioGestionColeccion\Domain\Entities\EventoCicloIot;

interface EventoCicloIotRepository
{
    public function guardar(EventoCicloIot $evento): void;

    public function buscarUltimoPorAgregadoYTipo(string $agregadoId, string $tipoEvento): ?EventoCicloIot;
}
