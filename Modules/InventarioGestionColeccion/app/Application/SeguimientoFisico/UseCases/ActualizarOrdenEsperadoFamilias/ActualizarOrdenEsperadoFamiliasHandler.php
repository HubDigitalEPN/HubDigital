<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarOrdenEsperadoFamilias;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\OrdenEsperadoFamiliasRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\OrdenEsperadoFamilias;

/**
 * Caso de uso: definir la secuencia de familias taxonómicas que el curador espera encontrar
 * en la colección, usada como referencia para evaluar el orden taxonómico de las cajas.
 *
 * @see ActualizarOrdenEsperadoFamiliasInput
 * @see ActualizarOrdenEsperadoFamiliasOutput
 */
final class ActualizarOrdenEsperadoFamiliasHandler
{
    /**
     * @param  OrdenEsperadoFamiliasRepository  $ordenFamiliasRepo  Persiste la secuencia esperada de familias.
     */
    public function __construct(
        private readonly OrdenEsperadoFamiliasRepository $ordenFamiliasRepo,
    ) {}

    /**
     * Construye el value object de orden esperado a partir de la lista del input (que lo
     * normaliza y deduplica), lo persiste y devuelve la secuencia resultante.
     */
    public function handle(ActualizarOrdenEsperadoFamiliasInput $input): ActualizarOrdenEsperadoFamiliasOutput
    {
        $orden = OrdenEsperadoFamilias::desde($input->familias);

        $this->ordenFamiliasRepo->guardar($orden);

        return new ActualizarOrdenEsperadoFamiliasOutput($orden->familias());
    }
}
