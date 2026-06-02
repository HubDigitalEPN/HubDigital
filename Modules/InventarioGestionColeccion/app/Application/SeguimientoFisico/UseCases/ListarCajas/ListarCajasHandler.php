<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarCajas;

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\CrearCaja\CrearCajaOutput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\CajaRepository;

final class ListarCajasHandler
{
    public function __construct(
        private readonly CajaRepository $cajaRepo,
    ) {}

    public function handle(): ListarCajasOutput
    {
        $cajas = $this->cajaRepo->buscarTodas();

        $items = array_map(
            fn ($c) => new CrearCajaOutput(
                id: (string) $c->id(),
                codigo: (string) $c->codigo(),
                codigoRfid: $c->codigoRfid() ? (string) $c->codigoRfid() : '',
                esEspecial: $c->esEspecial(),
                observacion: $c->observacion(),
                nombre: $c->nombre(),
                estado: $c->estadoActual()->valor(),
            ),
            $cajas,
        );

        return new ListarCajasOutput($items);
    }
}
