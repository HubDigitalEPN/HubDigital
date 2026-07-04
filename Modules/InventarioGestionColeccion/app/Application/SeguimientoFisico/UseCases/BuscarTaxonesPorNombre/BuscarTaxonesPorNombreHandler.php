<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\BuscarTaxonesPorNombre;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Taxon;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\TaxonRepositoryInterface;

/**
 * Búsqueda libre de taxones canónicos por nombre científico. Alimenta el
 * selector "reasignar a otro nombre" de la bandeja de revisión de taxa, para
 * que el curador no quede limitado a los candidatos sugeridos por similitud.
 */
final class BuscarTaxonesPorNombreHandler
{
    public function __construct(
        private readonly TaxonRepositoryInterface $taxonRepo,
    ) {}

    public function handle(BuscarTaxonesPorNombreInput $input): BuscarTaxonesPorNombreOutput
    {
        $consulta = trim($input->consulta);
        if ($consulta === '') {
            return new BuscarTaxonesPorNombreOutput([]);
        }

        $encontrados = array_slice(
            $this->taxonRepo->buscarPorNombreContiene($consulta),
            0,
            max(1, $input->limite),
        );

        $items = array_map(
            fn (Taxon $t): array => [
                'id' => (string) $t->id(),
                'nombreCientifico' => $t->nombreCientifico(),
                'rango' => $t->rango()->value,
            ],
            $encontrados,
        );

        return new BuscarTaxonesPorNombreOutput($items);
    }
}
