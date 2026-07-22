<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RestaurarEstadoEspecimenes;

final readonly class RestaurarEstadoEspecimenesInput
{
    /**
     * @param  string[]  $especimenIds  UUIDs de `taxonomia.especimenes` que vuelven del préstamo.
     * @param  string[]  $conNovedad  Subconjunto de `$especimenIds` que volvió con observación.
     *                                Son IDs de espécimen: la traducción desde los ítems del
     *                                préstamo es responsabilidad de quien invoca.
     */
    public function __construct(
        public array $especimenIds,
        public array $conNovedad = [],
    ) {}
}
