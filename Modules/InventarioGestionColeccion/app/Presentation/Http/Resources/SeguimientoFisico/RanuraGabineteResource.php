<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Resources\SeguimientoFisico;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\CrearRanuraGabinete\CrearRanuraGabineteOutput;

/** @property CrearRanuraGabineteOutput $resource */
final class RanuraGabineteResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'gabinete_id' => $this->resource->gabineteId,
            'numero_ranura' => $this->resource->numeroRanura,
            'familia_taxonomica_esperada_id' => $this->resource->familiaTaxonomicaEsperadaId,
            'activa' => $this->resource->activa,
        ];
    }
}
