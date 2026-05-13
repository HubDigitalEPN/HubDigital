<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Requests\SeguimientoFisico\GestionRegistrosTaxonomicos;

use Illuminate\Foundation\Http\FormRequest;

final class BuscarEspecimenesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'criterio' => ['required', 'string', 'in:taxon,localidad,estado'],
            'valor' => ['required', 'string', 'max:255'],
        ];
    }
}
