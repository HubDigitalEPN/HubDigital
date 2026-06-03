<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarCatalogNumberDuplicados;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;

/**
 * Lista grupos de especímenes que comparten el mismo `catalog_number`.
 *
 * Spec §5.3: hay 3.963 occurrence_ids repetidos y catalog_numbers reusados.
 * Cuando dos especímenes tienen el mismo catalog_number pero fechas distintas,
 * suelen ser DOS eventos legítimos (ej. MEPN:INV:1 con 1994 y 2006), no un error
 * de catalogación. El curador decide caso por caso.
 */
final class ListarCatalogNumberDuplicadosHandler
{
    public function __construct(
        private readonly EspecimenRepositoryInterface $especimenRepo,
    ) {}

    public function handle(ListarCatalogNumberDuplicadosInput $input): ListarCatalogNumberDuplicadosOutput
    {
        $minimo = max(2, $input->minimoDuplicados);

        /** @var array<string, Especimen[]> $grupos */
        $grupos = [];
        foreach ($this->especimenRepo->buscarTodos() as $especimen) {
            $catalogNumber = $especimen->catalogNumber();
            if ($catalogNumber === null || $catalogNumber === '') {
                continue;
            }
            $grupos[$catalogNumber][] = $especimen;
        }

        $items = [];
        foreach ($grupos as $catalogNumber => $especimenes) {
            if (count($especimenes) < $minimo) {
                continue;
            }

            $fechas = array_unique(array_map(fn (Especimen $e) => $e->fechaColecta(), $especimenes));

            $items[] = new ListarCatalogNumberDuplicadosItem(
                catalogNumber: (string) $catalogNumber,
                total: count($especimenes),
                especimenes: array_map(
                    fn (Especimen $e) => [
                        'id' => (string) $e->id(),
                        'codigoCatalogo' => $e->codigoCatalogo(),
                        'fechaColecta' => $e->fechaColecta(),
                        'colector' => $e->colector(),
                    ],
                    $especimenes,
                ),
                fechasDistintas: count($fechas) > 1,
            );
        }

        usort($items, fn (ListarCatalogNumberDuplicadosItem $a, ListarCatalogNumberDuplicadosItem $b) => $b->total <=> $a->total);

        return new ListarCatalogNumberDuplicadosOutput(items: $items, totalGrupos: count($items));
    }
}
