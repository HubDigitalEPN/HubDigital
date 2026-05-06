<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Resources\SeguimientoFisico;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarRetiroCaja\RegistrarRetiroCajaOutput;

/** @property RegistrarRetiroCajaOutput $resource */
final class RegistrarRetiroCajaResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'caja_id' => $this->resource->cajaId,
            'estado_caja' => $this->resource->estadoCaja,
            'alerta_generada' => $this->resource->alertaGenerada,
            'notificacion_enviada' => $this->resource->notificacionEnviada,
        ];
    }
}
