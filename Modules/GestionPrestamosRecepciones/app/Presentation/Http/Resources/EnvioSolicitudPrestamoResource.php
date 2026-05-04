<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class EnvioSolicitudPrestamoResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'solicitud_id'     => $this->resource->solicitudId,
            'numero_solicitud' => $this->resource->numeroSolicitud,
            'estado'           => $this->resource->estado->value,
            'enviada_en'       => $this->resource->enviadaEn?->format(\DateTimeInterface::ATOM),
        ];
    }
}
