<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\SanearDatosDwCDeposito;

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ResolverEntidadDepositante\ResolverEntidadDepositanteHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ResolverEntidadDepositante\ResolverEntidadDepositanteInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\FilaCatalogoMapper;

/**
 * Recupera los campos Darwin Core que se perdieron en los ingresos antiguos.
 *
 * Durante meses el traspaso desde el módulo de recepciones mapeaba treinta y dos campos
 * de la plantilla y no se los pasaba a la entidad, y la jerarquía taxonómica declarada no
 * tenía siquiera dónde guardarse. El material afectado sigue en la colección con esas
 * columnas en blanco aunque la matriz de origen sí traía los valores.
 *
 * Relee la matriz, la pasa por el mismo mapeo que usa el ingreso y **escribe únicamente
 * donde hay hueco**: lo que ya tiene valor no se toca, porque pudo haberlo puesto un
 * curador a mano y su criterio manda sobre el de una reconstrucción.
 *
 * Es idempotente: correrlo dos veces no cambia nada la segunda vez.
 */
final class SanearDatosDwCDepositoHandler
{
    public function __construct(
        private readonly EspecimenRepositoryInterface $especimenRepo,
        private readonly FilaCatalogoMapper $mapper,
        private readonly ResolverEntidadDepositanteHandler $resolverEntidad,
    ) {}

    public function handle(SanearDatosDwCDepositoInput $input): SanearDatosDwCDepositoOutput
    {
        $especimenesPorIndice = $this->especimenRepo->especimenesPorIndiceDeSolicitud($input->solicitudDepositoId);

        $entidadDepositanteId = $this->resolverDepositante($input);

        $especimenesTocados = 0;
        $columnasEscritas = 0;

        foreach ($input->filasPorIndice as $indice => $fila) {
            $especimen = $especimenesPorIndice[$indice] ?? null;

            if ($especimen === null) {
                continue;
            }

            $columnas = $this->columnasRecuperables($fila);
            $columnas['entidad_depositante_id'] = $entidadDepositanteId;

            if ($input->simular) {
                $columnasEscritas += count(array_filter(
                    $columnas,
                    static fn ($v): bool => $v !== null && $v !== '' && $v !== []
                ));
                $especimenesTocados++;

                continue;
            }

            $escritas = $this->especimenRepo->rellenarCamposVacios($especimen['id'], $columnas);

            if ($escritas > 0) {
                $especimenesTocados++;
                $columnasEscritas += $escritas;
            }
        }

        return new SanearDatosDwCDepositoOutput(
            especimenesTocados: $especimenesTocados,
            columnasEscritas: $columnasEscritas,
            simulado: $input->simular,
        );
    }

    /**
     * Entidad depositante del lote, resuelta una sola vez.
     *
     * En simulación tampoco se crea: dar de alta una institución es un efecto real, y el
     * sentido de la corrida en seco es que no ocurra ninguno.
     */
    private function resolverDepositante(SanearDatosDwCDepositoInput $input): ?string
    {
        $nombre = trim((string) $input->depositanteNombre);
        $institucion = trim((string) $input->depositanteInstitucion);

        if ($input->simular || ($nombre === '' && $institucion === '')) {
            return null;
        }

        return $this->resolverEntidad->handle(new ResolverEntidadDepositanteInput(
            nombrePersona: $nombre,
            institucion: $institucion === '' ? null : $institucion,
            email: $input->depositanteEmail,
        ))->entidadId;
    }

    /**
     * Columnas que se pueden reconstruir desde el registro Darwin Core de la matriz.
     *
     * @param  array<string, mixed>  $datosDwC
     * @return array<string, mixed>
     */
    private function columnasRecuperables(array $datosDwC): array
    {
        $mapeada = $this->mapper->mapear($datosDwC);

        return [
            // Plantilla v2: lo que el ingreso mapeaba y no pasaba.
            'record_number' => $mapeada->recordNumber,
            'origin' => $mapeada->origin,
            'identified_by' => $mapeada->identifiedBy,
            'date_determined' => $mapeada->dateDetermined,
            'research_permit' => $mapeada->researchPermit,
            'transport_permit' => $mapeada->transportPermit,
            'export_import_authorization' => $mapeada->exportImportAuthorization,
            'scientific_name_authorship' => $mapeada->scientificNameAuthorship,
            'lat_lon_max_error' => $mapeada->latLonMaxError,
            'clade' => $mapeada->clade,
            'identification_qualifier' => $mapeada->identificationQualifier,
            'identification_remarks' => $mapeada->identificationRemarks,
            'vernacular_name' => $mapeada->vernacularName,
            'type_notes' => $mapeada->typeNotes,
            'continent' => $mapeada->continent,
            'country_code' => $mapeada->countryCode,
            'locality_notes' => $mapeada->localityNotes,
            'locality_code' => $mapeada->localityCode,
            'elevation_max_error' => $mapeada->elevationMaxError,
            'verbatim_elevation' => $mapeada->verbatimElevation,
            'verbatim_depth' => $mapeada->verbatimDepth,
            'verbatim_latitude' => $mapeada->verbatimLatitude,
            'verbatim_longitude' => $mapeada->verbatimLongitude,
            'verbatim_coordinate_system' => $mapeada->verbatimCoordinateSystem,
            'verbatim_srs' => $mapeada->verbatimSrs,
            'information_withheld' => $mapeada->informationWithheld,
            'prior_owner' => $mapeada->priorOwner,
            'located_at' => $mapeada->locatedAt,
            'ipt_upload' => $mapeada->iptUpload,
            'record_created_by' => $mapeada->recordCreatedBy,
            'responsible_researcher_export' => $mapeada->responsibleResearcherExport,
            'endemic_verbatim' => $mapeada->endemicVerbatim,
            // Fecha de fin de colecta: su cabecera nunca casaba con el mapeo.
            'fecha_colecta_fin' => $mapeada->fechaColectaFin,
            // Jerarquía declarada, columnas sin destino y catch-all.
            ...$mapeada->darwinCoreExtendido()->paraPersistencia(),
        ];
    }
}
