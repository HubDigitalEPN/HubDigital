<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ValidarDocumentacionInicialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'provincia_origen' => ['required', 'string', 'max:100'],
            'documentos_adjuntos' => ['required', 'array'],
            'documentos_adjuntos.*' => ['string', 'max:500'],
        ];
    }
}
