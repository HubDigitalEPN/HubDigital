<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\CrearGabinete\CrearGabineteHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\CrearGabinete\CrearGabineteInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarGabinetes\ListarGabineteHandler;
use Modules\InventarioGestionColeccion\Presentation\Http\Requests\SeguimientoFisico\CrearGabineteRequest;
use Modules\InventarioGestionColeccion\Presentation\Http\Resources\SeguimientoFisico\GabineteResource;

final class GabineteController extends Controller
{
    public function __construct(
        private readonly CrearGabineteHandler $crearHandler,
        private readonly ListarGabineteHandler $listarHandler,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $output = $this->listarHandler->handle();

        return GabineteResource::collection($output->items);
    }

    public function store(CrearGabineteRequest $request): JsonResponse
    {
        $input = new CrearGabineteInput(
            codigo: $request->validated('codigo'),
            nombre: $request->validated('nombre'),
            totalRanuras: $request->validated('total_ranuras'),
        );

        $output = $this->crearHandler->handle($input);

        return (new GabineteResource($output))
            ->response()
            ->setStatusCode(201);
    }
}
