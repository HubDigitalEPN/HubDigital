<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** Valida cargas multipart reales; nunca acepta rutas o claves aportadas por el cliente. */
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
            'documentos' => ['required', 'array', 'min:1', 'max:10'],
            'documentos.*' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'documentos.*.max' => 'Cada PDF debe tener como máximo 10 MB.',
            'documentos.*.uploaded' => 'No se pudo cargar el PDF. Su tamaño máximo es de 10 MB.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $documentos = $this->allFiles()['documentos'] ?? $this->input('documentos', []);
            foreach (array_keys(is_array($documentos) ? $documentos : []) as $nombre) {
                if (is_string($nombre)
                    && (mb_strlen($nombre) > 160 || preg_match('/[\x00-\x1F\x7F]/u', $nombre) === 1)
                ) {
                    $v->errors()->add('documentos', 'Cada nombre lógico debe tener hasta 160 caracteres y no incluir controles.');
                    break;
                }

                if (! is_string($nombre) || trim((string) $nombre) === '') {
                    $v->errors()->add('documentos', 'Cada clave del array documentos debe ser un string no vacío.');
                    break;
                }
            }
        });
    }
}
