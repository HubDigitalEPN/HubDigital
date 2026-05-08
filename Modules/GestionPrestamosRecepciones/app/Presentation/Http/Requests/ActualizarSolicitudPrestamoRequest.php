<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ActualizarSolicitudPrestamoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'investigador_id'          => ['required', 'string'],
            'titulo_estudio'           => ['required', 'string', 'max:255'],
            'institucion_adscripcion'  => ['required', 'string', 'max:255'],
            'linea_investigacion'      => ['required', 'string', 'max:255'],
            'proposito_prestamo'       => ['required', 'string'],
            'duracion_propuesta_meses' => ['required', 'integer', 'min:1'],
            'justificacion_extendida'  => ['nullable', 'string'],
            'items'                    => ['required', 'array', 'min:1'],
            'items.*.id'                          => ['nullable', 'string'],
            'items.*.especimen_codigo_externo'     => ['required', 'string'],
            'items.*.cantidad_solicitada'          => ['required', 'integer', 'min:1'],
        ];
    }
}
