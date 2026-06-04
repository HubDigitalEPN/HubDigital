<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Requests\SeguimientoFisico\GestionRegistrosTaxonomicos;

use Illuminate\Foundation\Http\FormRequest;

final class ActualizarTaxonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nombre_cientifico' => ['required', 'string', 'max:255'],
            'autor' => ['nullable', 'string', 'max:255'],
            'anio_descripcion' => ['nullable', 'integer', 'min:1700', 'max:2100'],
            'epiteto_infraespecifico' => ['nullable', 'string', 'max:120'],
        ];
    }
}
