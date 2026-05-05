<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ValidarIdentidadSolicitudRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nombre_perfil' => ['required', 'string', 'max:255'],
            'nombre_en_documento' => ['required', 'string', 'max:255'],
        ];
    }
}
