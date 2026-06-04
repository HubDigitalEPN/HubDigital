<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarEspecimenesAsignables;

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\ClasificacionTaxonomicaPort;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\TaxonRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UnitTrayEspecimenRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\ClasificacionTaxonomica;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\UnitTrayId;

final class ListarEspecimenesAsignablesHandler
{
    public function __construct(
        private readonly EspecimenRepositoryInterface $especimenRepo,
        private readonly TaxonRepositoryInterface $taxonRepo,
        private readonly UnitTrayEspecimenRepository $asignacionRepo,
        private readonly ClasificacionTaxonomicaPort $clasificacionPort,
    ) {}

    public function handle(ListarEspecimenesAsignablesInput $input): ListarEspecimenesAsignablesOutput
    {
        // Los especímenes ya asignados al tray en contexto se incluyen siempre,
        // para que el curador pueda verlos/desmarcarlos aunque la búsqueda no los
        // devuelva. El resto de la lista es una proyección acotada por $limite:
        // jamás se hidrata el catálogo completo (decenas de miles de filas).
        $incluirSiempre = $input->unitTrayId !== null
            ? $this->asignacionRepo->especimenIdsPorUnitTray(UnitTrayId::desde($input->unitTrayId))
            : [];

        $filas = $this->especimenRepo->buscarParaAsignacion(
            $input->busqueda,
            $input->limite,
            $incluirSiempre,
        );

        $taxonIds = array_values(array_unique(array_filter(
            array_map(fn (array $f) => $f['taxonId'], $filas),
            fn (?string $id) => $id !== null && $id !== '',
        )));

        // Breadcrumb completo (orden › familia › … › especie) por taxón único.
        // Se cachea por taxonId para no resolver el mismo árbol más de una vez.
        $taxonomiaPorTaxon = [];
        $nombresPorTaxon = [];
        if ($taxonIds !== []) {
            foreach ($this->taxonRepo->buscarPorIds($taxonIds) as $taxon) {
                $nombresPorTaxon[(string) $taxon->id()] = $taxon->nombreCientifico();
            }
            foreach ($taxonIds as $taxonId) {
                $clasificacion = $this->clasificacionPort->resolverParaTaxon($taxonId);
                $taxonomiaPorTaxon[$taxonId] = $clasificacion !== null
                    ? $this->formatearTaxonomia($clasificacion)
                    : null;
            }
        }

        $especimenIds = array_map(fn (array $f) => $f['id'], $filas);
        $unitTrayPorEspecimen = $this->asignacionRepo->unitTraysDeEspecimenes($especimenIds);

        $items = array_map(function (array $f) use ($nombresPorTaxon, $taxonomiaPorTaxon, $unitTrayPorEspecimen): array {
            $taxonId = $f['taxonId'];
            $tieneTaxon = $taxonId !== null && $taxonId !== '';

            return [
                'id' => $f['id'],
                'codigoCatalogo' => $f['codigoCatalogo'],
                // Preferir el árbol completo; si la taxonomía no expone niveles
                // (p. ej. solo reino), caer al nombre del taxón inmediato.
                'taxonNombre' => $tieneTaxon
                    ? ($taxonomiaPorTaxon[$taxonId] ?? $nombresPorTaxon[$taxonId] ?? $taxonId)
                    : '—',
                'unitTrayId' => $unitTrayPorEspecimen[$f['id']] ?? null,
            ];
        }, $filas);

        return new ListarEspecimenesAsignablesOutput(items: $items);
    }

    /**
     * Arma el breadcrumb taxonómico de mayor a menor rango, omitiendo los niveles
     * nulos. Devuelve null si no hay ningún nivel con dato.
     */
    private function formatearTaxonomia(ClasificacionTaxonomica $c): ?string
    {
        $niveles = array_filter([
            $c->orden(),
            $c->suborden(),
            $c->superfamilia(),
            $c->familia(),
            $c->subfamilia(),
            $c->genero(),
            $c->especie(),
        ], fn (?string $v) => $v !== null && $v !== '');

        return $niveles === [] ? null : implode(' › ', $niveles);
    }
}
