<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SubirActaFirmadaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'investigador_id' => ['required', 'string'],
            'pdf_firmado'     => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }
}
