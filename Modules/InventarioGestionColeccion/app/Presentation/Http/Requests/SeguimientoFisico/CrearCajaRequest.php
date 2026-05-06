<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Requests\SeguimientoFisico;

use Illuminate\Foundation\Http\FormRequest;

final class CrearCajaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'max:100'],
            'codigo_rfid' => ['required', 'string', 'size:8', 'regex:/^[0-9A-Fa-f]{8}$/'],
            'nombre' => ['nullable', 'string', 'max:255'],
            'familia_taxonomica_id' => ['nullable', 'string', 'max:255'],
            'capacidad_maxima' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
