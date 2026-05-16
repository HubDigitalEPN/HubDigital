<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarEntidadDepositante\ActualizarEntidadDepositanteHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarEntidadDepositante\ActualizarEntidadDepositanteInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarEntidadDepositante\RegistrarEntidadDepositanteHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarEntidadDepositante\RegistrarEntidadDepositanteInput;
use Modules\InventarioGestionColeccion\Presentation\Http\Requests\SeguimientoFisico\GestionRegistrosTaxonomicos\ActualizarEntidadDepositanteRequest;
use Modules\InventarioGestionColeccion\Presentation\Http\Requests\SeguimientoFisico\GestionRegistrosTaxonomicos\RegistrarEntidadDepositanteRequest;
use Modules\InventarioGestionColeccion\Presentation\Http\Resources\SeguimientoFisico\GestionRegistrosTaxonomicos\EntidadDepositanteResource;

final class EntidadDepositanteController extends Controller
{
    public function __construct(
        private readonly RegistrarEntidadDepositanteHandler $registrarHandler,
        private readonly ActualizarEntidadDepositanteHandler $actualizarHandler,
    ) {}

    public function registrar(RegistrarEntidadDepositanteRequest $request): JsonResponse
    {
        $output = $this->registrarHandler->handle(new RegistrarEntidadDepositanteInput(
            nombre: $request->validated('nombre'),
            tipo: $request->validated('tipo'),
            contacto: $request->validated('contacto'),
        ));

        return (new EntidadDepositanteResource($output))
            ->response()
            ->setStatusCode(201);
    }

    public function actualizar(ActualizarEntidadDepositanteRequest $request, string $id): JsonResponse
    {
        $output = $this->actualizarHandler->handle(new ActualizarEntidadDepositanteInput(
            entidadId: $id,
            nombre: $request->validated('nombre'),
            tipo: $request->validated('tipo'),
            contacto: $request->validated('contacto'),
        ));

        return (new EntidadDepositanteResource($output))
            ->response()
            ->setStatusCode(200);
    }
}
