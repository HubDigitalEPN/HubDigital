<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Resources\SeguimientoFisico\GestionRegistrosTaxonomicos;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\GenerarActaEntrega\GenerarActaEntregaOutput;

/** @property GenerarActaEntregaOutput $resource */
final class ActaEntregaResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'entidad_id' => $this->resource->entidadId,
            'entidad_nombre' => $this->resource->entidadNombre,
            'total_especimenes' => $this->resource->totalEspecimenes,
            'pdf_ruta' => $this->resource->pdfRuta,
        ];
    }
}
