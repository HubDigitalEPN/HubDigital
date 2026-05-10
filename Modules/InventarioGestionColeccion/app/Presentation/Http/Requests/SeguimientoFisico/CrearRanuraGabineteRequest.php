<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Requests\SeguimientoFisico;

use Illuminate\Foundation\Http\FormRequest;

final class CrearRanuraGabineteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<string>> */
    public function rules(): array
    {
        return [
            'numero_ranura' => ['required', 'integer', 'min:1', 'max:25'],
            'familia_taxonomica_esperada_id' => ['nullable', 'string', 'max:255'],
        ];
    }
}
