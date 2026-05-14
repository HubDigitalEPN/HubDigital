<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Requests\SeguimientoFisico\GestionRegistrosTaxonomicos;

use Illuminate\Foundation\Http\FormRequest;

final class RegistrarEspecimenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'codigo_catalogo' => ['required', 'string', 'max:100'],
            'taxon_id' => ['required', 'string', 'uuid'],
            'localidad' => ['required', 'string', 'max:255'],
            'fecha_colecta' => ['required', 'date_format:Y-m-d'],
            'colector' => ['required', 'string', 'max:255'],
            'entidad_depositante_id' => ['nullable', 'string', 'uuid'],
            'occurrence_id' => ['nullable', 'string', 'max:120'],
            'catalog_number' => ['nullable', 'string', 'max:120'],
            'old_code' => ['nullable', 'string', 'max:120'],
            'cardex_liquid_collection_code' => ['nullable', 'string', 'max:120'],
            'individual_count' => ['nullable', 'integer', 'min:0'],
            'preparations' => ['nullable', 'string', 'max:120'],
            'disposition' => ['nullable', 'string', 'max:120'],
            'occurrence_status' => ['nullable', 'string', 'max:120'],
            'specimen_notes' => ['nullable', 'string'],
            'country' => ['nullable', 'string', 'max:120'],
            'state_province' => ['nullable', 'string', 'max:120'],
            'municipality' => ['nullable', 'string', 'max:120'],
            'locality_name' => ['nullable', 'string', 'max:500'],
            'decimal_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'decimal_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'geodetic_datum' => ['nullable', 'string', 'max:60'],
            'elevation_in_meters' => ['nullable', 'numeric'],
            'biome' => ['nullable', 'string', 'max:120'],
            'habitat' => ['nullable', 'string', 'max:255'],
            'identificadores' => ['nullable', 'array'],
            'identificadores.*.tipo' => ['required_with:identificadores', 'string', 'max:80'],
            'identificadores.*.valor' => ['required_with:identificadores', 'string', 'max:255'],
        ];
    }
}
