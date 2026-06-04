<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Requests\SeguimientoFisico\GestionRegistrosTaxonomicos;

use Illuminate\Foundation\Http\FormRequest;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TipoEntidadDepositante;

final class ActualizarEntidadDepositanteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tipos = implode(',', array_column(TipoEntidadDepositante::cases(), 'value'));

        return [
            'nombre' => ['required', 'string', 'max:255'],
            'tipo' => ['nullable', 'string', "in:{$tipos}"],
            'contacto' => ['nullable', 'string', 'max:255'],
        ];
    }
}
