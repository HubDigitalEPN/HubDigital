<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Resources\SeguimientoFisico;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ProcesarEventoEsp32\ProcesarEventoEsp32Output;

/**
 * Serializa a JSON la respuesta que la API devuelve al ESP32 tras procesar un evento
 * de barrido: confirma cómo quedó la caja (ranura asignada, estado) e informa si el
 * evento disparó una alerta o una notificación, para que el firmware no tenga que
 * inferirlo.
 *
 * @property ProcesarEventoEsp32Output $resource
 */
final class ProcesarEventoEsp32Resource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'caja_id' => $this->resource->cajaId,
            'ranura_id' => $this->resource->ranuraId,
            'estado_caja' => $this->resource->estadoCaja,
            'alerta_generada' => $this->resource->alertaGenerada,
            'notificacion_enviada' => $this->resource->notificacionEnviada,
        ];
    }
}
