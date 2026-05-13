<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Resources\SeguimientoFisico\GestionRegistrosTaxonomicos;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class EntidadDepositanteResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->resource->id,
            'nombre' => $this->resource->nombre,
            'tipo' => $this->resource->tipo,
            'contacto' => $this->resource->contacto,
        ];
    }
}
