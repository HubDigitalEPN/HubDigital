<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\OrdenEsperadoFamilias;

/**
 * Contrato de persistencia para el {@see OrdenEsperadoFamilias}: la secuencia
 * taxonómica esperada de familias con la que se contrasta el orden físico de las
 * cajas en el gabinete. Es configuración única de la colección.
 */
interface OrdenEsperadoFamiliasRepository
{
    /** Devuelve el orden esperado de familias configurado para la colección. */
    public function obtener(): OrdenEsperadoFamilias;

    /** Persiste el orden esperado de familias. */
    public function guardar(OrdenEsperadoFamilias $orden): void;
}
