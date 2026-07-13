<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Requests\SeguimientoFisico;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el snapshot completo del barrido que el ESP32 envía al finalizar un ciclo TDM.
 * Cada entrada de lecturas representa una ranura física escaneada; tag_uid null indica
 * ranura vacía confirmada. Slots ausentes del array se ignoran (barrido parcial por falla de lector).
 */
final class ProcesarSincronizacionEsp32Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'gabinete_id' => ['required', 'string', 'uuid'],
            'lecturas' => ['required', 'array', 'min:1', 'max:25'],
            'lecturas.*.slot_index' => ['required', 'integer', 'min:0', 'max:24', 'distinct'],
            'lecturas.*.tag_uid' => ['nullable', 'string', 'size:8', 'regex:/^[0-9A-Fa-f]{8}$/'],
        ];
    }
}
