<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;

interface EspecimenRepositoryInterface
{
    public function nextIdentity(): EspecimenId;

    public function guardar(Especimen $especimen): void;

    public function buscarPorId(EspecimenId $id): ?Especimen;

    /** @return Especimen[] */
    public function buscarPorEntidadDepositante(string $entidadDepositanteId): array;

    /** @return Especimen[] */
    public function buscarPorLocalidad(string $localidad): array;

    /** @return Especimen[] */
    public function buscarPorEstado(string $estado): array;

    /** @param string[] $taxonIds
     *  @return Especimen[] */
    public function buscarPorTaxonIds(array $taxonIds): array;

    public function buscarPorCodigoCatalogo(string $codigo): ?Especimen;

    /** @return Especimen[] */
    public function buscarPorIdentificador(string $tipo, string $valor): array;

    /** @return Especimen[] */
    public function buscarTodos(): array;
}
