<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Resources\SeguimientoFisico;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\CrearCaja\CrearCajaOutput;

/** @property CrearCajaOutput $resource */
final class CajaResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'codigo' => $this->resource->codigo,
            'codigo_rfid' => $this->resource->codigoRfid,
            'nombre' => $this->resource->nombre,
            'familia_taxonomica_id' => $this->resource->familiaTaxonomicaId,
            'capacidad_maxima' => $this->resource->capacidadMaxima,
            'estado' => $this->resource->estado,
        ];
    }
}
