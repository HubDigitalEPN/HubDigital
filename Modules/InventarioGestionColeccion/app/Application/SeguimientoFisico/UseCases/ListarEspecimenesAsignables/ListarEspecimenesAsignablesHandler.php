<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarEspecimenesAsignables;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\TaxonRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UnitTrayEspecimenRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\UnitTrayId;

final class ListarEspecimenesAsignablesHandler
{
    public function __construct(
        private readonly EspecimenRepositoryInterface $especimenRepo,
        private readonly TaxonRepositoryInterface $taxonRepo,
        private readonly UnitTrayEspecimenRepository $asignacionRepo,
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

        // Solo el nombre científico del taxón inmediato: una sola query por
        // lote (buscarPorIds). Resolver el árbol completo por espécimen
        // disparaba decenas de SELECTs secuenciales contra el pooler remoto
        // y agotaba el tiempo de ejecución.
        $taxonIds = array_values(array_unique(array_filter(
            array_map(fn (array $f) => $f['taxonId'], $filas),
            fn (?string $id) => $id !== null && $id !== '',
        )));

        $nombresPorTaxon = [];
        if ($taxonIds !== []) {
            foreach ($this->taxonRepo->buscarPorIds($taxonIds) as $taxon) {
                $nombresPorTaxon[(string) $taxon->id()] = $taxon->nombreCientifico();
            }
        }

        $especimenIds = array_map(fn (array $f) => $f['id'], $filas);
        $unitTrayPorEspecimen = $this->asignacionRepo->unitTraysDeEspecimenes($especimenIds);

        $items = array_map(function (array $f) use ($nombresPorTaxon, $unitTrayPorEspecimen): array {
            $taxonId = $f['taxonId'];

            return [
                'id' => $f['id'],
                'codigoCatalogo' => $f['codigoCatalogo'],
                'taxonNombre' => $taxonId !== null && $taxonId !== ''
                    ? ($nombresPorTaxon[$taxonId] ?? $taxonId)
                    : '—',
                'unitTrayId' => $unitTrayPorEspecimen[$f['id']] ?? null,
            ];
        }, $filas);

        return new ListarEspecimenesAsignablesOutput(items: $items);
    }
}
