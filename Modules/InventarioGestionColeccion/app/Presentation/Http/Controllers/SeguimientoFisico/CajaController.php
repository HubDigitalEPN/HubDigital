<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\CrearCaja\CrearCajaHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\CrearCaja\CrearCajaInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarCajas\ListarCajasHandler;
use Modules\InventarioGestionColeccion\Presentation\Http\Requests\SeguimientoFisico\CrearCajaRequest;
use Modules\InventarioGestionColeccion\Presentation\Http\Resources\SeguimientoFisico\CajaResource;

final class CajaController extends Controller
{
    public function __construct(
        private readonly CrearCajaHandler $crearHandler,
        private readonly ListarCajasHandler $listarHandler,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $output = $this->listarHandler->handle();

        return CajaResource::collection($output->items);
    }

    public function store(CrearCajaRequest $request): JsonResponse
    {
        $input = new CrearCajaInput(
            codigo: $request->validated('codigo'),
            codigoRfid: $request->validated('codigo_rfid'),
            nombre: $request->validated('nombre'),
            familiaTaxonomicaId: $request->validated('familia_taxonomica_id'),
            capacidadMaxima: $request->validated('capacidad_maxima'),
        );

        $output = $this->crearHandler->handle($input);

        return (new CajaResource($output))
            ->response()
            ->setStatusCode(201);
    }
}
