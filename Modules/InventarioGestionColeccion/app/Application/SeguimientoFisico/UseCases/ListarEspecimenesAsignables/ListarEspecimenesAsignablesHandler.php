<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarEspecimenesAsignables;

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\UbicacionEspecimenPort;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\TaxonRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UnitTrayEspecimenRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\UnitTrayId;

/**
 * Caso de uso: listar especímenes candidatos a asignarse a un unit tray, mediante una búsqueda
 * acotada (nunca hidrata el catálogo completo). Devuelve cada espécimen con su nombre científico
 * y el unit tray al que ya está asignado, e incluye siempre los del tray en contexto.
 *
 * @see ListarEspecimenesAsignablesInput
 * @see ListarEspecimenesAsignablesOutput
 */
final class ListarEspecimenesAsignablesHandler
{
    /**
     * @param  EspecimenRepositoryInterface  $especimenRepo  Busca los especímenes candidatos acotados por la consulta.
     * @param  TaxonRepositoryInterface  $taxonRepo  Resuelve el nombre científico de los taxones en un solo lote.
     * @param  UnitTrayEspecimenRepository  $asignacionRepo  Resuelve qué unit tray ocupa cada espécimen.
     * @param  UbicacionEspecimenPort  $ubicacionPort  Resuelve la ubicación física legible de cada espécimen en un solo lote.
     */
    public function __construct(
        private readonly EspecimenRepositoryInterface $especimenRepo,
        private readonly TaxonRepositoryInterface $taxonRepo,
        private readonly UnitTrayEspecimenRepository $asignacionRepo,
        private readonly UbicacionEspecimenPort $ubicacionPort,
    ) {}

    /**
     * Busca los especímenes que coinciden con el filtro (más los ya asignados al tray en
     * contexto), resuelve su nombre científico en un único lote y anexa el unit tray actual de
     * cada uno, devolviendo una proyección lista para la UI de asignación.
     */
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

        // Ubicación física legible de cada espécimen en un solo lote: permite distinguir,
        // entre varios especímenes con el mismo nombre científico, dónde está cada uno.
        $ubicacionPorEspecimen = $this->ubicacionPort->ubicacionesPorEspecimenes($especimenIds);

        $items = array_map(function (array $f) use ($nombresPorTaxon, $unitTrayPorEspecimen, $ubicacionPorEspecimen): array {
            $taxonId = $f['taxonId'];

            return [
                'id' => $f['id'],
                'codigoCatalogo' => $f['codigoCatalogo'],
                'taxonNombre' => $taxonId !== null && $taxonId !== ''
                    ? ($nombresPorTaxon[$taxonId] ?? $taxonId)
                    : '—',
                'unitTrayId' => $unitTrayPorEspecimen[$f['id']] ?? null,
                'ubicacion' => $ubicacionPorEspecimen[$f['id']] ?? null,
            ];
        }, $filas);

        return new ListarEspecimenesAsignablesOutput(items: $items);
    }
}
