<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Requests\SeguimientoFisico;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateHorarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<string>> */
    public function rules(): array
    {
        return [
            'hora_inicio' => ['required', 'integer', 'min:0', 'max:23'],
            'hora_fin' => ['required', 'integer', 'min:0', 'max:23'],
        ];
    }

    protected function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ((int) $this->hora_inicio >= (int) $this->hora_fin) {
                $validator->errors()->add(
                    'hora_fin',
                    'La hora de fin debe ser mayor que la hora de inicio.'
                );
            }
        });
    }
}
