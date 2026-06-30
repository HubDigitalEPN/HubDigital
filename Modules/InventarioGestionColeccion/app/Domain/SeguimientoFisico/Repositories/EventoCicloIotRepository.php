<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\EventoCicloIot;

interface EventoCicloIotRepository
{
    public function guardar(EventoCicloIot $evento): void;

    public function buscarUltimoPorAgregadoYTipo(string $agregadoId, string $tipoEvento): ?EventoCicloIot;

    /**
     * Devuelve todos los eventos de un agregado (p. ej. todos los movimientos de un
     * espécimen, unit tray o caja), ordenados cronológicamente de forma ascendente.
     *
     * @return EventoCicloIot[]
     */
    public function buscarPorAgregado(string $tipoAgregado, string $agregadoId): array;
}
