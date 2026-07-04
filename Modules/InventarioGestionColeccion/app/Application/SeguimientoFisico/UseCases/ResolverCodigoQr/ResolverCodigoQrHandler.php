<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ResolverCodigoQr;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Exceptions\EspecimenNoEncontradoException;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\CodigoQrRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\TaxonRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TaxonId;

final class ResolverCodigoQrHandler
{
    public function __construct(
        private readonly CodigoQrRepositoryInterface $codigoQrRepo,
        private readonly EspecimenRepositoryInterface $especimenRepo,
        private readonly TaxonRepositoryInterface $taxonRepo,
    ) {}

    public function handle(ResolverCodigoQrInput $input): ResolverCodigoQrOutput
    {
        $codigoQr = $this->codigoQrRepo->buscarPorPayload($input->payload);

        if ($codigoQr === null) {
            throw new EspecimenNoEncontradoException($input->payload);
        }

        $especimen = $this->especimenRepo->buscarPorId(EspecimenId::desde($codigoQr->especimenId()));

        if ($especimen === null) {
            throw new EspecimenNoEncontradoException($codigoQr->especimenId());
        }

        // Resuelve el nombre científico legible (heurística de usabilidad: nunca
        // mostrar solo el id crudo del taxón). Null si el espécimen aún no está
        // determinado.
        $taxonNombre = null;
        if ($especimen->taxonId() !== null) {
            $taxon = $this->taxonRepo->buscarPorId(TaxonId::desde($especimen->taxonId()));
            $taxonNombre = $taxon?->nombreCientifico();
        }

        return new ResolverCodigoQrOutput(
            id: (string) $especimen->id(),
            codigoCatalogo: $especimen->codigoCatalogo(),
            taxonId: $especimen->taxonId(),
            taxonNombre: $taxonNombre,
            localidad: $especimen->localidad(),
            fechaColecta: $especimen->fechaColecta(),
            colector: $especimen->colector(),
            estado: $especimen->estado()->value,
            entidadDepositanteId: $especimen->entidadDepositanteId(),
            datos: $this->mapaCompleto($especimen, $taxonNombre),
        );
    }

    /**
     * Proyección COMPLETA del espécimen, keyed por la misma `clave` del registro
     * de columnas (RegistroColumnasEspecimen), para pintar la ficha entera del QR
     * agrupada y etiquetada. Valores ya formateados como texto (o null si vacío).
     *
     * @return array<string, ?string>
     */
    private function mapaCompleto(Especimen $e, ?string $taxonNombre): array
    {
        $texto = fn ($v): ?string => ($v === null || $v === '') ? null : (string) $v;

        return [
            // Identificación
            'codigoCatalogo' => $texto($e->codigoCatalogo()),
            'occurrenceId' => $texto($e->occurrenceId()),
            'catalogNumber' => $texto($e->catalogNumber()),
            'oldCode' => $texto($e->oldCode()),
            'cardexLiquidCollectionCode' => $texto($e->cardexLiquidCollectionCode()),
            'filaOrigenExcel' => $texto($e->filaOrigenExcel()),
            // Taxonomía
            'taxonNombre' => $texto($taxonNombre),
            'taxonVerbatim' => $texto($e->taxonVerbatim()),
            // Localidad
            'localidad' => $texto($e->localidad()),
            'localidadVerbatim' => $texto($e->localidadVerbatim()),
            'localityName' => $texto($e->localityName()),
            'country' => $texto($e->country()),
            'stateProvince' => $texto($e->stateProvince()),
            'municipality' => $texto($e->municipality()),
            'decimalLatitude' => $texto($e->decimalLatitude()),
            'decimalLongitude' => $texto($e->decimalLongitude()),
            'coordVerbatim' => $texto($e->coordVerbatim()),
            'geodeticDatum' => $texto($e->geodeticDatum()),
            'elevationMinM' => $texto($e->elevationMinM()),
            'elevationMaxM' => $texto($e->elevationMaxM()),
            // Fecha
            'fechaColecta' => $texto($e->fechaColecta()),
            'fechaColectaFin' => $texto($e->fechaColectaFin()),
            'fechaVerbatim' => $texto($e->fechaVerbatim()),
            // Registro
            'colector' => $texto($e->colector()),
            'individualCount' => $texto($e->individualCount()),
            'individualCountVerbatim' => $texto($e->individualCountVerbatim()),
            'preparations' => $texto($e->preparations()),
            'disposition' => $texto($e->disposition()),
            'occurrenceStatus' => $texto($e->occurrenceStatus()),
            'actaRecepcion' => $texto($e->actaRecepcion()),
            'estado' => $texto($e->estado()->value),
            // Atributos
            'sex' => $texto($e->sex()),
            'lifeStage' => $texto($e->lifeStage()),
            'caste' => $texto($e->caste()),
            'typeStatus' => $texto($e->typeStatus()),
            'biome' => $texto($e->biome()),
            'habitat' => $texto($e->habitat()),
            'microhabitat' => $texto($e->microhabitat()),
            'biogeographicRegion' => $texto($e->biogeographicRegion()),
            'endemic' => $e->endemic() === null ? null : ($e->endemic() ? 'Sí' : 'No'),
            // Revisión
            'estadoRevision' => $texto($e->estadoRevision()->value),
            'motivoRevision' => $texto($e->motivoRevision()),
        ];
    }
}
