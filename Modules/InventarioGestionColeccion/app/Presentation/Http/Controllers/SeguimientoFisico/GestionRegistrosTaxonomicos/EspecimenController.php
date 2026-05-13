<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\BuscarEspecimenes\BuscarEspecimenesHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\BuscarEspecimenes\BuscarEspecimenesInput;
use Modules\InventarioGestionColeccion\Presentation\Http\Requests\SeguimientoFisico\GestionRegistrosTaxonomicos\BuscarEspecimenesRequest;
use Modules\InventarioGestionColeccion\Presentation\Http\Resources\SeguimientoFisico\GestionRegistrosTaxonomicos\EspecimenesResource;

final class EspecimenController extends Controller
{
    public function __construct(
        private readonly BuscarEspecimenesHandler $buscarHandler,
    ) {}

    public function buscar(BuscarEspecimenesRequest $request): JsonResponse
    {
        $output = $this->buscarHandler->handle(new BuscarEspecimenesInput(
            criterio: $request->validated('criterio'),
            valor: $request->validated('valor'),
        ));

        return (new EspecimenesResource($output))
            ->response()
            ->setStatusCode(200);
    }
}
