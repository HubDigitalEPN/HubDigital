<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Identificacion;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\IdentificacionId;

interface IdentificacionRepositoryInterface
{
    public function nextIdentity(): IdentificacionId;

    public function guardar(Identificacion $identificacion): void;

    public function buscarPorId(IdentificacionId $id): ?Identificacion;

    /**
     * Identificaciones del espécimen ordenadas: la actual primero, luego históricas
     * por fecha_determinacion desc.
     *
     * @return Identificacion[]
     */
    public function listarPorEspecimen(EspecimenId $especimenId): array;

    public function buscarActualDe(EspecimenId $especimenId): ?Identificacion;

    /**
     * Marca todas las identificaciones del espécimen como no-actuales.
     * Usado por el caso de uso IdentificarEspecimen antes de insertar la nueva.
     */
    public function desactualizarTodasDe(EspecimenId $especimenId): void;
}
