<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarEspecimenesSinCoordenadas;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\TaxonRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services\MapeadorFilaEspecimen;

/**
 * Lista los especímenes SIN coordenadas (falta latitud o longitud), para la
 * bandeja de Localidades del Control de calidad. Incluye los que además no
 * tienen localidad (`sinLocalidad`), y expone la "ubicación anterior"
 * (`localidadVerbatim`) para los registros cuya ubicación administrativa fue
 * reemplazada por la de sus coordenadas durante el import.
 */
final class ListarEspecimenesSinCoordenadasHandler
{
    public function __construct(
        private readonly EspecimenRepositoryInterface $especimenRepo,
        private readonly TaxonRepositoryInterface $taxonRepo,
    ) {}

    public function handle(ListarEspecimenesSinCoordenadasInput $input): ListarEspecimenesSinCoordenadasOutput
    {
        $perPage = max(1, $input->perPage);
        $page = max(1, $input->page);
        $offset = ($page - 1) * $perPage;

        $total = $this->especimenRepo->contarSinCoordenadas();
        $totalPaginas = $total > 0 ? (int) ceil($total / $perPage) : 1;

        $especimenes = $this->especimenRepo->buscarSinCoordenadas($perPage, $offset);

        $taxonIds = array_values(array_unique(array_filter(
            array_map(fn (Especimen $e) => $e->taxonId(), $especimenes)
        )));
        $nombres = [];
        if ($taxonIds !== []) {
            foreach ($this->taxonRepo->buscarPorIds($taxonIds) as $taxon) {
                $nombres[(string) $taxon->id()] = $taxon->nombreCientifico();
            }
        }

        $items = array_map(function (Especimen $e) use ($nombres): array {
            $fila = MapeadorFilaEspecimen::mapear(
                $e,
                $e->taxonId() !== null ? ($nombres[$e->taxonId()] ?? null) : null,
            );
            $fila['sinLocalidad'] = trim((string) $e->localidad()) === ''
                && ($e->localityName() === null || trim($e->localityName()) === '');

            return $fila;
        }, $especimenes);

        return new ListarEspecimenesSinCoordenadasOutput(
            items: $items,
            total: $total,
            page: $page,
            perPage: $perPage,
            totalPaginas: $totalPaginas,
        );
    }
}
