<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ProcesarEventoEsp32\ProcesarEventoEsp32Handler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ProcesarEventoEsp32\ProcesarEventoEsp32Input;
use Modules\InventarioGestionColeccion\Presentation\Http\Requests\SeguimientoFisico\ProcesarEventoEsp32Request;
use Modules\InventarioGestionColeccion\Presentation\Http\Resources\SeguimientoFisico\ProcesarEventoEsp32Resource;

/**
 * Punto de entrada HTTP de la API REST que consume el ESP32. Traduce la petición
 * validada al Input del caso de uso, delega la lógica en el Handler y envuelve el
 * resultado en un Resource JSON; no contiene reglas de negocio (capa de presentación).
 */
final class SeguimientoFisicoController extends Controller
{
    /**
     * @param  ProcesarEventoEsp32Handler  $eventoEsp32Handler  Caso de uso que ingiere el
     *                                                          evento de barrido del ESP32 y coordina ubicación, alertas y notificaciones.
     */
    public function __construct(
        private readonly ProcesarEventoEsp32Handler $eventoEsp32Handler,
    ) {}

    /**
     * Recibe un evento de presencia del ESP32 (tag entró o salió de una ranura), lo
     * arma como Input, ejecuta el caso de uso y responde 200 con el estado resultante
     * de la caja para que el firmware confirme el registro.
     */
    public function procesarEvento(ProcesarEventoEsp32Request $request): JsonResponse
    {
        $input = new ProcesarEventoEsp32Input(
            tagUid: $request->validated('tag_uid'),
            gabineteId: $request->validated('gabinete_id'),
            slotIndex: $request->validated('slot_index'),
            evento: $request->validated('evento'),
        );

        $output = $this->eventoEsp32Handler->handle($input);

        return (new ProcesarEventoEsp32Resource($output))
            ->response()
            ->setStatusCode(200);
    }
}
