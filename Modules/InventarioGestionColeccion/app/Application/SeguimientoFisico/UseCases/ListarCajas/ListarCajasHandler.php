<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarCajas;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\CajaRepository;

final class ListarCajasHandler
{
    public function __construct(
        private readonly CajaRepository $cajaRepo,
    ) {}

    public function handle(): ListarCajasOutput
    {
        $cajas = $this->cajaRepo->buscarTodas();

        $items = array_map(function ($c): CajaListadoItem {
            $clasificacion = $c->clasificacionTaxonomica();

            return new CajaListadoItem(
                id: (string) $c->id(),
                codigo: (string) $c->codigo(),
                codigoRfid: $c->codigoRfid() ? (string) $c->codigoRfid() : '',
                esEspecial: $c->esEspecial(),
                observacion: $c->observacion(),
                nombre: $c->nombre(),
                estado: $c->estadoActual()->valor(),
                subfamilia: $clasificacion?->subfamilia(),
                genero: $clasificacion?->genero(),
                especie: $clasificacion?->especie(),
            );
        }, $cajas);

        return new ListarCajasOutput($items);
    }
}
