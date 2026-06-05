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
 *
 * Estrategia escalable:
 *  - Paso 1: GROUP BY + COUNT con HAVING (SQL-side, paginado).
 *  - Paso 2: WHERE catalog_number IN (...) para traer los especímenes del page.
 */
final class ListarCatalogNumberDuplicadosHandler
{
    public function __construct(
        private readonly EspecimenRepositoryInterface $especimenRepo,
    ) {}

    public function handle(ListarCatalogNumberDuplicadosInput $input): ListarCatalogNumberDuplicadosOutput
    {
        $minimo = max(2, $input->minimoDuplicados);
        $pagina = max(1, $input->pagina);
        $porPagina = max(1, min(100, $input->porPagina));
        $offset = ($pagina - 1) * $porPagina;

        $grupos = $this->especimenRepo->listarCatalogNumbersDuplicados($minimo, $porPagina, $offset);
        $total = $this->especimenRepo->contarGruposCatalogNumberDuplicados($minimo);

        // Una sola query para los especímenes de todos los catalog_numbers de la página.
        $especimenes = $this->especimenRepo->buscarPorCatalogNumbersIn(array_keys($grupos));

        /** @var array<string, Especimen[]> $porCatalog */
        $porCatalog = [];
        foreach ($especimenes as $e) {
            $cn = (string) $e->catalogNumber();
            $porCatalog[$cn][] = $e;
        }

        $items = [];
        foreach ($grupos as $catalogNumber => $conteo) {
            $delGrupo = $porCatalog[(string) $catalogNumber] ?? [];
            $fechas = array_unique(array_map(fn (Especimen $e) => $e->fechaColecta(), $delGrupo));

            $items[] = new ListarCatalogNumberDuplicadosItem(
                catalogNumber: (string) $catalogNumber,
                total: $conteo,
                especimenes: array_map(
                    fn (Especimen $e) => [
                        'id' => (string) $e->id(),
                        'codigoCatalogo' => $e->codigoCatalogo(),
                        'fechaColecta' => $e->fechaColecta(),
                        'colector' => $e->colector(),
                        'estadoRevision' => $e->estadoRevision()->value,
                    ],
                    $delGrupo,
                ),
                fechasDistintas: count(array_filter($fechas, fn ($f) => $f !== '')) > 1,
            );
        }

        return new ListarCatalogNumberDuplicadosOutput(
            items: $items,
            totalGrupos: $total,
            pagina: $pagina,
            porPagina: $porPagina,
        );
    }
}
