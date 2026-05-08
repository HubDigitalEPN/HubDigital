<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ActaFirmadaResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'acta_id'           => $this->resource->actaId,
            'numero_prestamo'   => $this->resource->numeroPrestamo,
            'estado_acta'       => $this->resource->estadoActa,
            'pdf_firmado_ruta'  => $this->resource->pdfFirmadoRuta,
            'firmada_subida_en' => $this->resource->firmadaSubidaEn?->format(\DateTimeInterface::ATOM),
        ];
    }
}
