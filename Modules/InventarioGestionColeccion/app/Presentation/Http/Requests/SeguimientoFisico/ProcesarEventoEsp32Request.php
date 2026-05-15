<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Requests\SeguimientoFisico;

use Illuminate\Foundation\Http\FormRequest;

final class ProcesarEventoEsp32Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tag_uid' => ['required', 'string', 'size:8', 'regex:/^[0-9A-Fa-f]{8}$/'],
            'gabinete_id' => ['required', 'string', 'uuid'],
            'slot_index' => ['required', 'integer', 'min:0', 'max:24'],
            'evento' => ['required', 'string', 'in:ingreso,retiro'],
        ];
    }
}
