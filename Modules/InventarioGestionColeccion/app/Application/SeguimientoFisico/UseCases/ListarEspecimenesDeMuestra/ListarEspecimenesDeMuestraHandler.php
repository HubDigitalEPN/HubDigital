<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarEspecimenesDeMuestra;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\TaxonRepositoryInterface;

/**
 * Drill-down de la bandeja de muestras: lista los especímenes concretos que
 * contiene una muestra (lote de colecta), con los datos relevantes para
 * verificarla (nombre científico, localidad, fecha, colector).
 */
final class ListarEspecimenesDeMuestraHandler
{
    public function __construct(
        private readonly EspecimenRepositoryInterface $especimenRepo,
        private readonly TaxonRepositoryInterface $taxonRepo,
    ) {}

    public function handle(ListarEspecimenesDeMuestraInput $input): ListarEspecimenesDeMuestraOutput
    {
        $muestraId = trim($input->muestraId);
        if ($muestraId === '') {
            return new ListarEspecimenesDeMuestraOutput([]);
        }

        $especimenes = $this->especimenRepo->buscarPorMuestraId($muestraId, $input->limite);

        $taxonIds = array_values(array_unique(array_filter(
            array_map(fn (Especimen $e) => $e->taxonId(), $especimenes)
        )));
        $nombresTaxon = [];
        if ($taxonIds !== []) {
            foreach ($this->taxonRepo->buscarPorIds($taxonIds) as $taxon) {
                $nombresTaxon[(string) $taxon->id()] = $taxon->nombreCientifico();
            }
        }

        $items = array_map(
            fn (Especimen $e): array => [
                'id' => (string) $e->id(),
                'codigoCatalogo' => $e->codigoCatalogo(),
                'taxonNombre' => $e->taxonId() !== null ? ($nombresTaxon[$e->taxonId()] ?? null) : null,
                'localidad' => $e->localidad(),
                'fechaColecta' => $e->fechaColecta(),
                'colector' => $e->colector(),
            ],
            $especimenes,
        );

        return new ListarEspecimenesDeMuestraOutput($items);
    }
}
