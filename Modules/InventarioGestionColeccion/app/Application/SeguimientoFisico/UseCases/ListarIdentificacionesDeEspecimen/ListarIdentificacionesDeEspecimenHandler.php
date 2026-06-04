<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarIdentificacionesDeEspecimen;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Identificacion;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\IdentificacionRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\TaxonRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;

final class ListarIdentificacionesDeEspecimenHandler
{
    public function __construct(
        private readonly IdentificacionRepositoryInterface $identificacionRepo,
        private readonly TaxonRepositoryInterface $taxonRepo,
    ) {}

    public function handle(ListarIdentificacionesDeEspecimenInput $input): ListarIdentificacionesDeEspecimenOutput
    {
        $identificaciones = $this->identificacionRepo->listarPorEspecimen(EspecimenId::desde($input->especimenId));

        $taxonIds = array_values(array_unique(array_filter(
            array_map(fn (Identificacion $i) => $i->taxonId() !== null ? (string) $i->taxonId() : null, $identificaciones)
        )));

        $taxonesMap = [];
        if ($taxonIds !== []) {
            foreach ($this->taxonRepo->buscarPorIds($taxonIds) as $taxon) {
                $taxonesMap[(string) $taxon->id()] = $taxon->nombreCientifico();
            }
        }

        $items = array_map(
            fn (Identificacion $i) => new ListarIdentificacionesDeEspecimenItemOutput(
                id: (string) $i->id(),
                taxonId: $i->taxonId() !== null ? (string) $i->taxonId() : null,
                taxonNombre: $i->taxonId() !== null ? ($taxonesMap[(string) $i->taxonId()] ?? null) : null,
                identificadoPor: $i->identificadoPor(),
                fechaDeterminacion: $i->fechaDeterminacion()?->format('Y-m-d'),
                calificador: $i->calificador(),
                observaciones: $i->observaciones(),
                esActual: $i->esActual(),
            ),
            $identificaciones,
        );

        return new ListarIdentificacionesDeEspecimenOutput(items: $items, total: count($items));
    }
}
