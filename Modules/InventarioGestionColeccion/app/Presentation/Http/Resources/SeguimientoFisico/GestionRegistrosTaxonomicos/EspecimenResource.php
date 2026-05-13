<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Resources\SeguimientoFisico\GestionRegistrosTaxonomicos;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarEspecimen\RegistrarEspecimenOutput;

/** @property RegistrarEspecimenOutput $resource */
final class EspecimenResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->resource->id,
            'codigo_catalogo' => $this->resource->codigoCatalogo,
            'taxon_id' => $this->resource->taxonId,
            'localidad' => $this->resource->localidad,
            'fecha_colecta' => $this->resource->fechaColecta,
            'colector' => $this->resource->colector,
            'estado' => $this->resource->estado,
            'entidad_depositante_id' => $this->resource->entidadDepositanteId,
        ];
    }
}
