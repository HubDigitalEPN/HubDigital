<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class CargarDocumentacionOficialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'documentos' => ['required', 'array', 'min:1'],
            'documentos.*' => ['string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            foreach (array_keys($this->input('documentos', [])) as $nombre) {
                if (! is_string($nombre) || trim((string) $nombre) === '') {
                    $v->errors()->add('documentos', 'Cada clave del array documentos debe ser un string no vacío.');
                    break;
                }
            }
        });
    }
}
