<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Visitante;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\VisitanteId;

interface VisitanteRepositoryInterface
{
    public function nextIdentity(): VisitanteId;

    public function guardar(Visitante $visitante): void;

    public function buscarPorId(VisitanteId $id): ?Visitante;

    /** @return Visitante[] */
    public function buscarTodos(): array;
}
