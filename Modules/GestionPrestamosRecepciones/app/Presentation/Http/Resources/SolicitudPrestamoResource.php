<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class SolicitudPrestamoResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'solicitud_id'             => $this->resource->solicitudId,
            'numero_solicitud'         => $this->resource->numeroSolicitud,
            'estado'                   => $this->resource->estado->value,
            'titulo_estudio'           => $this->resource->tituloEstudio,
            'institucion_adscripcion'  => $this->resource->institucionAdscripcion,
            'linea_investigacion'      => $this->resource->lineaInvestigacion,
            'proposito_prestamo'       => $this->resource->propositoPrestamo,
            'duracion_propuesta_meses' => $this->resource->duracionPropuestaMeses,
            'items'                    => $this->resource->items,
        ];
    }
}
