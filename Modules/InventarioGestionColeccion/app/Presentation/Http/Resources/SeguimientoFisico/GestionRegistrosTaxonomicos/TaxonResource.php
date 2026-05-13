<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Resources\SeguimientoFisico\GestionRegistrosTaxonomicos;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class TaxonResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->resource->id,
            'nombre_cientifico' => $this->resource->nombreCientifico,
            'rango' => $this->resource->rango,
            'autor' => $this->resource->autor,
            'anio_descripcion' => $this->resource->anioDescripcion,
            'estado' => $this->resource->estado,
        ];
    }
}
