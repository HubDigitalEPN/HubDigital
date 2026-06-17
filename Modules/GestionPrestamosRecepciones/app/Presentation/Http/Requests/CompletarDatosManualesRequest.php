<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Valida que se reciba el campo y valor para completar manualmente un dato de un RegistroEspecimen. */
final class CompletarDatosManualesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'campo' => ['required', 'string', 'max:100'],
            'valor' => ['required', 'string', 'max:500'],
        ];
    }
}
