<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ProcesarSincronizacionEsp32\ProcesarSincronizacionEsp32Handler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ProcesarSincronizacionEsp32\ProcesarSincronizacionEsp32Input;
use Modules\InventarioGestionColeccion\Presentation\Http\Requests\SeguimientoFisico\ProcesarSincronizacionEsp32Request;
use Modules\InventarioGestionColeccion\Presentation\Http\Resources\SeguimientoFisico\ProcesarSincronizacionEsp32Resource;

/**
 * Punto de entrada HTTP que consume el ESP32 al finalizar cada barrido TDM.
 * Recibe el snapshot completo del gabinete y delega la reconciliación al handler;
 * no contiene reglas de negocio (capa de presentación pura).
 */
final class SeguimientoFisicoController extends Controller
{
    public function __construct(
        private readonly ProcesarSincronizacionEsp32Handler $sincronizacionHandler,
    ) {}

    /**
     * Recibe el snapshot completo de las 25 ranuras, lo mapea al Input del caso de uso
     * y devuelve el resumen de la reconciliación al firmware para su confirmación.
     */
    public function procesarSincronizacion(ProcesarSincronizacionEsp32Request $request): JsonResponse
    {
        $lecturas = array_map(
            fn (array $l) => [
                'slotIndex' => $l['slot_index'],
                'tagUid' => $l['tag_uid'] ?? null,
            ],
            $request->validated('lecturas'),
        );

        $input = new ProcesarSincronizacionEsp32Input(
            gabineteId: $request->validated('gabinete_id'),
            lecturas: $lecturas,
        );

        $output = $this->sincronizacionHandler->handle($input);

        return (new ProcesarSincronizacionEsp32Resource($output))
            ->response()
            ->setStatusCode(200);
    }
}
