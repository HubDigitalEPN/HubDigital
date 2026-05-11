<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ProcesarEventoEsp32\ProcesarEventoEsp32Handler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ProcesarEventoEsp32\ProcesarEventoEsp32Input;
use Modules\InventarioGestionColeccion\Presentation\Http\Requests\SeguimientoFisico\ProcesarEventoEsp32Request;
use Modules\InventarioGestionColeccion\Presentation\Http\Resources\SeguimientoFisico\ProcesarEventoEsp32Resource;

final class SeguimientoFisicoController extends Controller
{
    public function __construct(
        private readonly ProcesarEventoEsp32Handler $eventoEsp32Handler,
    ) {}

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
