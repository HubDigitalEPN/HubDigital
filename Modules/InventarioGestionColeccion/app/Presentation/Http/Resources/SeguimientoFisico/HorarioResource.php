<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Resources\SeguimientoFisico;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarHorario\ActualizarHorarioOutput;

/** @property ActualizarHorarioOutput|object $resource */
final class HorarioResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'hora_inicio' => $this->resource->horaInicio,
            'hora_fin' => $this->resource->horaFin,
            'activo' => $this->resource->activo,
        ];
    }
}
