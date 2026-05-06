<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Requests\SeguimientoFisico;

use Illuminate\Foundation\Http\FormRequest;

final class CrearGabineteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<string>> */
    public function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'max:100'],
            'nombre' => ['required', 'string', 'max:255'],
            'total_ranuras' => ['required', 'integer', 'min:1', 'max:25'],
        ];
    }
}
