<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarTaxon\ActualizarTaxonHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarTaxon\ActualizarTaxonInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarTaxon\RegistrarTaxonHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarTaxon\RegistrarTaxonInput;
use Modules\InventarioGestionColeccion\Presentation\Http\Requests\SeguimientoFisico\GestionRegistrosTaxonomicos\ActualizarTaxonRequest;
use Modules\InventarioGestionColeccion\Presentation\Http\Requests\SeguimientoFisico\GestionRegistrosTaxonomicos\RegistrarTaxonRequest;
use Modules\InventarioGestionColeccion\Presentation\Http\Resources\SeguimientoFisico\GestionRegistrosTaxonomicos\TaxonResource;

final class TaxonController extends Controller
{
    public function __construct(
        private readonly RegistrarTaxonHandler $registrarHandler,
        private readonly ActualizarTaxonHandler $actualizarHandler,
    ) {}

    public function registrar(RegistrarTaxonRequest $request): JsonResponse
    {
        $output = $this->registrarHandler->handle(new RegistrarTaxonInput(
            nombreCientifico: $request->validated('nombre_cientifico'),
            rango: $request->validated('rango'),
            autor: $request->validated('autor'),
            anioDescripcion: $request->validated('anio_descripcion'),
            padreId: $request->validated('padre_id'),
            epitetoInfraespecifico: $request->validated('epiteto_infraespecifico'),
        ));

        return (new TaxonResource($output))
            ->response()
            ->setStatusCode(201);
    }

    public function actualizar(ActualizarTaxonRequest $request, string $id): JsonResponse
    {
        $output = $this->actualizarHandler->handle(new ActualizarTaxonInput(
            taxonId: $id,
            nombreCientifico: $request->validated('nombre_cientifico'),
            autor: $request->validated('autor'),
            anioDescripcion: $request->validated('anio_descripcion'),
            epitetoInfraespecifico: $request->validated('epiteto_infraespecifico'),
        ));

        return (new TaxonResource($output))
            ->response()
            ->setStatusCode(200);
    }
}
