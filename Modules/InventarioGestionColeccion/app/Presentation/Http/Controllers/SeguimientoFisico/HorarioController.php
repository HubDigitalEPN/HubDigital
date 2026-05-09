<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarHorario\ActualizarHorarioHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarHorario\ActualizarHorarioInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ObtenerHorario\ObtenerHorarioHandler;
use Modules\InventarioGestionColeccion\Presentation\Http\Requests\SeguimientoFisico\UpdateHorarioRequest;
use Modules\InventarioGestionColeccion\Presentation\Http\Resources\SeguimientoFisico\HorarioResource;

final class HorarioController extends Controller
{
    public function show(ObtenerHorarioHandler $handler): JsonResponse
    {
        $output = $handler->handle();

        return response()->json(new HorarioResource($output));
    }

    public function update(
        UpdateHorarioRequest $request,
        ActualizarHorarioHandler $handler,
    ): JsonResponse {
        $input = new ActualizarHorarioInput(
            horaInicio: $request->validated('hora_inicio'),
            horaFin: $request->validated('hora_fin'),
        );

        $output = $handler->handle($input);

        return response()->json(new HorarioResource($output));
    }
}
