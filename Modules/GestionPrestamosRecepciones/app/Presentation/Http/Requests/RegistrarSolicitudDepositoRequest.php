<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Valida el tipo de trámite (Depósito o Donación) al iniciar una nueva SolicitudDeposito. */
final class RegistrarSolicitudDepositoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tipo_tramite' => ['required', 'string', 'in:Depósito,Donación'],
        ];
    }
}
