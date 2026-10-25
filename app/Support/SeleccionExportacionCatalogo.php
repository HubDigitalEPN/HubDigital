<?php

declare(strict_types=1);

namespace App\Support;

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\BuscarEspecimenes\BuscarEspecimenesHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\BuscarEspecimenes\BuscarEspecimenesInput;

final class SeleccionExportacionCatalogo
{
    /** Reutiliza los mismos filtros curatoriales, sin el límite de la página visible. */
    public static function ids(BuscarEspecimenesInput $filtros): array
    {
        $ids = []; $pagina = 1;
        do {
            $input = new BuscarEspecimenesInput(...array_replace(get_object_vars($filtros), ['page' => $pagina, 'perPage' => 500, 'permitirSinFiltros' => true]));
            $salida = app(BuscarEspecimenesHandler::class)->handle($input);
            array_push($ids, ...array_column($salida->items, 'id'));
            $pagina++;
        } while ($pagina <= $salida->totalPaginas);
        return array_values(array_unique($ids));
    }
}
