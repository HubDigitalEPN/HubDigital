<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SincronizarEspecimenesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'curador_id' => ['required', 'string'],
            'especimenes' => ['required', 'array', 'min:1'],
            'especimenes.*.occurrenceID' => ['required', 'string'],
            'especimenes.*.configuracion' => ['nullable', 'array'],
            'especimenes.*.configuracion.occurrenceIDVisible' => ['boolean'],
            'especimenes.*.configuracion.scientificNameVisible' => ['boolean'],
            'especimenes.*.configuracion.individualCountVisible' => ['boolean'],
            'especimenes.*.configuracion.typeStatusVisible' => ['boolean'],
            'especimenes.*.configuracion.typeNotesVisible' => ['boolean'],
            'especimenes.*.configuracion.specimenNotesVisible' => ['boolean'],
            'especimenes.*.configuracion.samplingProtocolVisible' => ['boolean'],
            'especimenes.*.configuracion.recordedByVisible' => ['boolean'],
            'especimenes.*.configuracion.occurrenceStatusVisible' => ['boolean'],
            'especimenes.*.configuracion.familyVisible' => ['boolean'],
            'especimenes.*.configuracion.genusVisible' => ['boolean'],
            'especimenes.*.configuracion.countryVisible' => ['boolean'],
            'especimenes.*.configuracion.localityNameVisible' => ['boolean'],
            'especimenes.*.configuracion.decimalLatitudeVisible' => ['boolean'],
            'especimenes.*.configuracion.decimalLongitudeVisible' => ['boolean'],
        ];
    }
}
