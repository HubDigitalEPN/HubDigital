<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Resources\SeguimientoFisico;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ProcesarSincronizacionEsp32\ProcesarSincronizacionEsp32Output;

/**
 * Serializa el resumen de la reconciliación del barrido para que el firmware
 * confirme que el snapshot fue procesado y pueda registrar el id de sincronización.
 *
 * @property ProcesarSincronizacionEsp32Output $resource
 */
final class ProcesarSincronizacionEsp32Resource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'sincronizacion_id' => $this->resource->sincronizacionId,
            'ingresos' => $this->resource->ingresos,
            'retiros' => $this->resource->retiros,
            'reubicaciones' => $this->resource->reubicaciones,
            'sin_cambios' => $this->resource->sinCambios,
            'alertas_generadas' => $this->resource->alertasGeneradas,
            'con_incongruencias' => $this->resource->conIncongruencias,
            'tags_desconocidos' => $this->resource->tagsDesconocidos,
            'slots_desconocidos' => $this->resource->slotsDesconocidos,
        ];
    }
}
