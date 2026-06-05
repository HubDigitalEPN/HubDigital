<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Localidad;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\LocalidadId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\RangoLocalidad;

interface LocalidadRepositoryInterface
{
    public function nextIdentity(): LocalidadId;

    public function guardar(Localidad $localidad): void;

    public function buscarPorId(LocalidadId $id): ?Localidad;

    public function buscarPorNombreYRango(string $nombreCanonico, RangoLocalidad $rango): ?Localidad;

    /** @return Localidad[] */
    public function buscarPorNombreContiene(string $fragmento): array;

    /** @return Localidad[] */
    public function buscarTodos(): array;

    /**
     * Cuenta total para paginación.
     */
    public function contarTodos(): int;

    /**
     * Devuelve una página de localidades ordenadas por nombre_canonico ASC.
     *
     * @return Localidad[]
     */
    public function listarPagina(int $offset, int $limit): array;

    /**
     * @param  string[]  $ids
     * @return Localidad[]
     */
    public function buscarPorIds(array $ids): array;
}
