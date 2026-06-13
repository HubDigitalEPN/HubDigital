<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarCajas;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\CajaRepository;

/**
 * Caso de uso: listar todas las cajas con sus datos y su clasificación taxonómica propagada,
 * para el inventario de cajas.
 *
 * @see ListarCajasItemOutput
 * @see ListarCajasOutput
 */
final class ListarCajasHandler
{
    /**
     * @param  CajaRepository  $cajaRepo  Recupera todas las cajas registradas.
     */
    public function __construct(
        private readonly CajaRepository $cajaRepo,
    ) {}

    /**
     * Recupera todas las cajas y las proyecta a items de salida, incluyendo subfamilia, género
     * y especie de su clasificación dominante.
     */
    public function handle(): ListarCajasOutput
    {
        $cajas = $this->cajaRepo->buscarTodas();

        $items = array_map(function ($c): ListarCajasItemOutput {
            $clasificacion = $c->clasificacionTaxonomica();

            return new ListarCajasItemOutput(
                id: (string) $c->id(),
                codigo: (string) $c->codigo(),
                codigoRfid: $c->codigoRfid() ? (string) $c->codigoRfid() : '',
                esEspecial: $c->esEspecial(),
                observacion: $c->observacion(),
                nombre: $c->nombre(),
                estado: $c->estadoActual()->valor(),
                orden: $clasificacion?->orden(),
                suborden: $clasificacion?->suborden(),
                superfamilia: $clasificacion?->superfamilia(),
                familia: $clasificacion?->familia(),
                subfamilia: $clasificacion?->subfamilia(),
                genero: $clasificacion?->genero(),
                especie: $clasificacion?->especie(),
                subfamilias: $clasificacion?->subfamilias() ?? [],
                generos: $clasificacion?->generos() ?? [],
            );
        }, $cajas);

        return new ListarCajasOutput($items);
    }
}
