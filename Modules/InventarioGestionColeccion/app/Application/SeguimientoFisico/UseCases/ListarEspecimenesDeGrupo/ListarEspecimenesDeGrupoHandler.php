<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarEspecimenesDeGrupo;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;

/**
 * Lista los especímenes concretos que caen en un grupo pendiente de una bandeja
 * de revisión (fecha / taxón / localidad verbatim). Permite que el curador
 * inspeccione el grupo y elija cuáles aprobar, en vez de aplicar a ciegas.
 */
final class ListarEspecimenesDeGrupoHandler
{
    public function __construct(
        private readonly EspecimenRepositoryInterface $especimenRepo,
    ) {}

    public function handle(ListarEspecimenesDeGrupoInput $input): ListarEspecimenesDeGrupoOutput
    {
        $verbatim = trim($input->verbatim);
        if ($verbatim === '') {
            return new ListarEspecimenesDeGrupoOutput([]);
        }

        $especimenes = match ($input->tipo) {
            ListarEspecimenesDeGrupoInput::TIPO_FECHA => $this->especimenRepo->buscarPorFechaVerbatimPendiente($verbatim, $input->limite),
            ListarEspecimenesDeGrupoInput::TIPO_TAXON => $this->especimenRepo->buscarPorTaxonVerbatimPendiente($verbatim, $input->limite),
            ListarEspecimenesDeGrupoInput::TIPO_LOCALIDAD => $this->especimenRepo->buscarPorLocalidadVerbatimPendiente($verbatim, $input->limite),
            default => throw new \InvalidArgumentException("Tipo de grupo inválido: '{$input->tipo}'."),
        };

        $items = array_map(
            fn (Especimen $e): array => [
                'id' => (string) $e->id(),
                'codigoCatalogo' => $e->codigoCatalogo(),
                'colector' => $e->colector(),
                'localidad' => $e->localidad(),
                'fechaVerbatim' => $e->fechaVerbatim(),
                'taxonVerbatim' => $e->taxonVerbatim(),
                'localidadVerbatim' => $e->localidadVerbatim(),
            ],
            $especimenes,
        );

        return new ListarEspecimenesDeGrupoOutput($items);
    }
}
