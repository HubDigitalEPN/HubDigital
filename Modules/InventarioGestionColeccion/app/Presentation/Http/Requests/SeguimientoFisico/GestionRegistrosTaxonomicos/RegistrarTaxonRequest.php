<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Requests\SeguimientoFisico\GestionRegistrosTaxonomicos;

use Illuminate\Foundation\Http\FormRequest;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\RangoTaxonomico;

final class RegistrarTaxonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rangos = implode(',', RangoTaxonomico::valoresAceptados());

        return [
            'nombre_cientifico' => ['required', 'string', 'max:255'],
            'rango' => ['required', 'string', "in:{$rangos}"],
            'autor' => ['required', 'string', 'max:255'],
            'anio_descripcion' => ['required', 'integer', 'min:1700', 'max:2100'],
            'padre_id' => ['nullable', 'string', 'uuid'],
        ];
    }
}
