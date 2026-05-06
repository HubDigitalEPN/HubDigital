<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\CrearRanuraGabinete\CrearRanuraGabineteHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\CrearRanuraGabinete\CrearRanuraGabineteInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarRanurasGabinete\ListarRanurasGabineteHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarRanurasGabinete\ListarRanurasGabineteInput;
use Modules\InventarioGestionColeccion\Presentation\Http\Requests\SeguimientoFisico\CrearRanuraGabineteRequest;
use Modules\InventarioGestionColeccion\Presentation\Http\Resources\SeguimientoFisico\RanuraGabineteResource;

final class RanuraGabineteController extends Controller
{
    public function __construct(
        private readonly CrearRanuraGabineteHandler $crearHandler,
        private readonly ListarRanurasGabineteHandler $listarHandler,
    ) {}

    public function index(string $gabinete_id): AnonymousResourceCollection
    {
        $output = $this->listarHandler->handle(new ListarRanurasGabineteInput($gabinete_id));

        return RanuraGabineteResource::collection($output->items);
    }

    public function store(CrearRanuraGabineteRequest $request, string $gabinete_id): JsonResponse
    {
        $input = new CrearRanuraGabineteInput(
            gabineteId: $gabinete_id,
            numeroRanura: $request->validated('numero_ranura'),
            familiaTaxonomicaEsperadaId: $request->validated('familia_taxonomica_esperada_id'),
        );

        $output = $this->crearHandler->handle($input);

        return (new RanuraGabineteResource($output))
            ->response()
            ->setStatusCode(201);
    }
}
