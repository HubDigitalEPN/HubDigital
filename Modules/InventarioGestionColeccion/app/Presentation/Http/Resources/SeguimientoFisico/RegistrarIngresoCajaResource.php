<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Resources\SeguimientoFisico;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarIngresoCaja\RegistrarIngresoCajaOutput;

/** @property RegistrarIngresoCajaOutput $resource */
final class RegistrarIngresoCajaResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'caja_id' => $this->resource->cajaId,
            'ranura_id' => $this->resource->ranuraId,
            'estado_caja' => $this->resource->estadoCaja,
            'ubicacion_caja_id' => $this->resource->ubicacionCajaId,
            'alerta_generada' => $this->resource->alertaGenerada,
        ];
    }
}
