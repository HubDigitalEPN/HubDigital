<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Resources\SeguimientoFisico\GestionRegistrosTaxonomicos;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\BuscarEspecimenes\BuscarEspecimenesOutput;

/** @property BuscarEspecimenesOutput $resource */
final class EspecimenesResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'items' => $this->resource->items,
            'total' => count($this->resource->items),
        ];
    }
}
