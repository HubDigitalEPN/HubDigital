<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\GenerarActaEntrega\GenerarActaEntregaHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\GenerarActaEntrega\GenerarActaEntregaInput;
use Modules\InventarioGestionColeccion\Presentation\Http\Resources\SeguimientoFisico\GestionRegistrosTaxonomicos\ActaEntregaResource;

final class ActaEntregaController extends Controller
{
    public function __construct(
        private readonly GenerarActaEntregaHandler $generarHandler,
    ) {}

    public function generar(string $entidadId): JsonResponse
    {
        $output = $this->generarHandler->handle(new GenerarActaEntregaInput(
            entidadDepositanteId: $entidadId,
        ));

        return (new ActaEntregaResource($output))
            ->response()
            ->setStatusCode(201);
    }
}
