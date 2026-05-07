<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ModificarConfiguracionDivulgacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'especimenes' => ['required', 'array', 'min:1'],
            'especimenes.*.occurrenceID' => ['required', 'string'],
            'especimenes.*.configuracion' => ['required', 'array'],
            'especimenes.*.configuracion.occurrenceIDVisible' => ['required', 'boolean'],
            'especimenes.*.configuracion.scientificNameVisible' => ['required', 'boolean'],
            'especimenes.*.configuracion.individualCountVisible' => ['required', 'boolean'],
            'especimenes.*.configuracion.typeStatusVisible' => ['required', 'boolean'],
            'especimenes.*.configuracion.typeNotesVisible' => ['required', 'boolean'],
            'especimenes.*.configuracion.specimenNotesVisible' => ['required', 'boolean'],
            'especimenes.*.configuracion.samplingProtocolVisible' => ['required', 'boolean'],
            'especimenes.*.configuracion.recordedByVisible' => ['required', 'boolean'],
            'especimenes.*.configuracion.occurrenceStatusVisible' => ['required', 'boolean'],
            'especimenes.*.configuracion.familyVisible' => ['required', 'boolean'],
            'especimenes.*.configuracion.genusVisible' => ['required', 'boolean'],
            'especimenes.*.configuracion.countryVisible' => ['required', 'boolean'],
            'especimenes.*.configuracion.localityNameVisible' => ['required', 'boolean'],
            'especimenes.*.configuracion.decimalLatitudeVisible' => ['required', 'boolean'],
            'especimenes.*.configuracion.decimalLongitudeVisible' => ['required', 'boolean'],
        ];
    }
}
