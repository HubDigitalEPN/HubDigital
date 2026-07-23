<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarMuestrasParaRevision;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\MuestraColectaRepositoryInterface;

final class ListarMuestrasParaRevisionHandler
{
    public function __construct(
        private readonly MuestraColectaRepositoryInterface $muestraRepo,
        private readonly EspecimenRepositoryInterface $especimenRepo,
    ) {}

    public function handle(ListarMuestrasParaRevisionInput $input): ListarMuestrasParaRevisionOutput
    {
        $pagina = max(1, $input->pagina);
        $porPagina = max(1, min(200, $input->porPagina));
        $offset = ($pagina - 1) * $porPagina;

        $total = $this->muestraRepo->contarParaRevision();
        $muestras = $this->muestraRepo->listarParaRevision($porPagina, $offset);

        $ids = array_map(fn ($m) => (string) $m->id(), $muestras);
        $conteos = $this->especimenRepo->contarPorMuestraIds($ids);
        // Localidad real derivada de los especímenes (la muestra casi nunca la
        // trae): reemplaza los campos verbatim que venían vacíos.
        $localidades = $this->especimenRepo->localidadRepresentativaPorMuestraIds($ids);

        $items = array_map(fn ($m) => [
            'id' => (string) $m->id(),
            'codigoMuestra' => $m->codigoMuestra(),
            'localidad' => $localidades[(string) $m->id()] ?? null,
            'colector' => $m->colector(),
            'fechaVerbatim' => $m->fechaVerbatim(),
            'estadoRevision' => $m->estadoRevision()->value,
            'motivoRevision' => $m->motivoRevision(),
            'conteoEspecimenes' => $conteos[(string) $m->id()] ?? 0,
        ], $muestras);

        return new ListarMuestrasParaRevisionOutput(
            items: $items,
            total: $total,
            pagina: $pagina,
            porPagina: $porPagina,
        );
    }
}
