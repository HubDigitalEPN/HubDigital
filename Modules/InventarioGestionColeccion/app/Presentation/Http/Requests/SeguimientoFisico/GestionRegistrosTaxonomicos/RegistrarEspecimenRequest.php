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
        ];
    }
}
