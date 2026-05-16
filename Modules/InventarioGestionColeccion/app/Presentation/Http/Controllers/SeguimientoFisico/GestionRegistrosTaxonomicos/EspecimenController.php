<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\BuscarEspecimenes\BuscarEspecimenesHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\BuscarEspecimenes\BuscarEspecimenesInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarEspecimen\RegistrarEspecimenHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarEspecimen\RegistrarEspecimenInput;
use Modules\InventarioGestionColeccion\Presentation\Http\Requests\SeguimientoFisico\GestionRegistrosTaxonomicos\BuscarEspecimenesRequest;
use Modules\InventarioGestionColeccion\Presentation\Http\Requests\SeguimientoFisico\GestionRegistrosTaxonomicos\RegistrarEspecimenRequest;
use Modules\InventarioGestionColeccion\Presentation\Http\Resources\SeguimientoFisico\GestionRegistrosTaxonomicos\EspecimenesResource;
use Modules\InventarioGestionColeccion\Presentation\Http\Resources\SeguimientoFisico\GestionRegistrosTaxonomicos\EspecimenResource;

final class EspecimenController extends Controller
{
    public function __construct(
        private readonly BuscarEspecimenesHandler $buscarHandler,
        private readonly RegistrarEspecimenHandler $registrarHandler,
    ) {}

    public function registrar(RegistrarEspecimenRequest $request): JsonResponse
    {
        $output = $this->registrarHandler->handle(new RegistrarEspecimenInput(
            codigoCatalogo: $request->validated('codigo_catalogo'),
            taxonId: $request->validated('taxon_id'),
            localidad: $request->validated('localidad'),
            fechaColecta: $request->validated('fecha_colecta'),
            colector: $request->validated('colector'),
            entidadDepositanteId: $request->validated('entidad_depositante_id'),
            occurrenceId: $request->validated('occurrence_id'),
            catalogNumber: $request->validated('catalog_number'),
            oldCode: $request->validated('old_code'),
            cardexLiquidCollectionCode: $request->validated('cardex_liquid_collection_code'),
            individualCount: $request->validated('individual_count') !== null ? (int) $request->validated('individual_count') : null,
            preparations: $request->validated('preparations'),
            disposition: $request->validated('disposition'),
            occurrenceStatus: $request->validated('occurrence_status'),
            specimenNotes: $request->validated('specimen_notes'),
            country: $request->validated('country'),
            stateProvince: $request->validated('state_province'),
            municipality: $request->validated('municipality'),
            localityName: $request->validated('locality_name'),
            decimalLatitude: $request->validated('decimal_latitude') !== null ? (float) $request->validated('decimal_latitude') : null,
            decimalLongitude: $request->validated('decimal_longitude') !== null ? (float) $request->validated('decimal_longitude') : null,
            geodeticDatum: $request->validated('geodetic_datum'),
            elevationInMeters: $request->validated('elevation_in_meters') !== null ? (float) $request->validated('elevation_in_meters') : null,
            biome: $request->validated('biome'),
            habitat: $request->validated('habitat'),
            identificadores: $request->validated('identificadores') ?? [],
        ));

        return (new EspecimenResource($output))
            ->response()
            ->setStatusCode(201);
    }

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
