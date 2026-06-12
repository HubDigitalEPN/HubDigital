<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Requests\SeguimientoFisico;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el cuerpo JSON que el ESP32 envía por la API REST cada vez que cambia la
 * presencia de un tag en una ranura. Es la primera barrera del componente: rechaza
 * payloads malformados antes de que lleguen al caso de uso de ingesta del barrido.
 */
final class ProcesarEventoEsp32Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas del evento del ESP32: el tag_uid es un UID RFID de 8 dígitos
     * hexadecimales, el gabinete_id es un UUID, el slot_index cae en el rango físico
     * 0–24 (25 ranuras) y el evento solo puede ser «ingreso» o «retiro».
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tag_uid' => ['required', 'string', 'size:8', 'regex:/^[0-9A-Fa-f]{8}$/'],
            'gabinete_id' => ['required', 'string', 'uuid'],
            'slot_index' => ['required', 'integer', 'min:0', 'max:24'],
            'evento' => ['required', 'string', 'in:ingreso,retiro'],
        ];
    }
}
