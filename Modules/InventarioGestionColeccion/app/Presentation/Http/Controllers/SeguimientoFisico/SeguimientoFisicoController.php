<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ProcesarEventoEsp32\ProcesarEventoEsp32Handler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ProcesarEventoEsp32\ProcesarEventoEsp32Input;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarIngresoCaja\RegistrarIngresoCajaHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarIngresoCaja\RegistrarIngresoCajaInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarRetiroCaja\RegistrarRetiroCajaHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarRetiroCaja\RegistrarRetiroCajaInput;
use Modules\InventarioGestionColeccion\Presentation\Http\Requests\SeguimientoFisico\ProcesarEventoEsp32Request;
use Modules\InventarioGestionColeccion\Presentation\Http\Requests\SeguimientoFisico\RegistrarIngresoCajaRequest;
use Modules\InventarioGestionColeccion\Presentation\Http\Requests\SeguimientoFisico\RegistrarRetiroCajaRequest;
use Modules\InventarioGestionColeccion\Presentation\Http\Resources\SeguimientoFisico\ProcesarEventoEsp32Resource;
use Modules\InventarioGestionColeccion\Presentation\Http\Resources\SeguimientoFisico\RegistrarIngresoCajaResource;
use Modules\InventarioGestionColeccion\Presentation\Http\Resources\SeguimientoFisico\RegistrarRetiroCajaResource;

final class SeguimientoFisicoController extends Controller
{
    public function __construct(
        private readonly RegistrarIngresoCajaHandler $ingresoCajaHandler,
        private readonly RegistrarRetiroCajaHandler $retiroCajaHandler,
        private readonly ProcesarEventoEsp32Handler $eventoEsp32Handler,
    ) {}

    public function registrarIngreso(RegistrarIngresoCajaRequest $request, string $caja_id): JsonResponse
    {
        $input = new RegistrarIngresoCajaInput(
            cajaId: $caja_id,
            ranuraId: $request->validated('ranura_id'),
        );

        $output = $this->ingresoCajaHandler->handle($input);

        return (new RegistrarIngresoCajaResource($output))
            ->response()
            ->setStatusCode(201);
    }

    public function registrarRetiro(RegistrarRetiroCajaRequest $request, string $caja_id): JsonResponse
    {
        $input = new RegistrarRetiroCajaInput(
            cajaId: $caja_id,
            ranuraId: $request->validated('ranura_id'),
        );

        $output = $this->retiroCajaHandler->handle($input);

        return (new RegistrarRetiroCajaResource($output))
            ->response()
            ->setStatusCode(200);
    }

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
