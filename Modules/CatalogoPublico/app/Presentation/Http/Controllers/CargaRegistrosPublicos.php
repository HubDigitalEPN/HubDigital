<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Presentation\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Modules\CatalogoPublico\Application\Ports\DatosEspecimenProveedor;
use Modules\CatalogoPublico\Application\Ports\ProveedorEspecimenesPort;
use Modules\CatalogoPublico\Domain\Entities\EspecimenDivulgable;
use Modules\CatalogoPublico\Domain\Repositories\EspecimenDivulgableRepositoryInterface;
use Modules\CatalogoPublico\Infrastructure\Adapters\StorageImagenesAdapter;
use Modules\CatalogoPublico\Infrastructure\CalidadDatoPublico;

/** La tabla, el mapa y el explorador comparten la misma proyección pública. */
trait CargaRegistrosPublicos
{
    /**
     * Imágenes agrupadas por occurrence_id para los especímenes visibles de la especie.
     *
     * @param  list<string>  $occurrenceIDs
     * @return array<string, list<array{url: string, nombre: string}>>
     */
    private function cargarImagenesPorEspecimen(array $occurrenceIDs): array
    {
        if ($occurrenceIDs === []) {
            return [];
        }

        $consulta = DB::table('divulgacion.imagenes_taxonomicas')
            ->whereIn('occurrence_id', $occurrenceIDs)
            ->where('disco', 'r2')
            ->whereRaw('(SELECT COUNT(*) FROM taxonomia.especimenes identidad WHERE identidad.occurrence_id = divulgacion.imagenes_taxonomicas.occurrence_id) = 1')
            ->selectRaw('occurrence_id, ruta, disco, nombre_original, ROW_NUMBER() OVER (PARTITION BY occurrence_id ORDER BY created_at, id) AS posicion');
        return DB::query()->fromSub($consulta, 'fotos_publicas')->where('posicion', '<=', 12)->orderBy('occurrence_id')->orderBy('posicion')
            ->get(['occurrence_id', 'ruta', 'disco', 'nombre_original'])
            ->groupBy('occurrence_id')
            ->map(fn ($grupo): array => $grupo->map(fn ($r): array => [
                'url' => StorageImagenesAdapter::urlPublica($r->ruta),
                'nombre' => $r->nombre_original,
            ])->values()->all())
            ->all();
    }

    /** @param list<string> $especimenIds @return list<object> */
    private function cargarDetallesPorEspecimenIds(
        array $especimenIds,
        ProveedorEspecimenesPort $proveedor,
        EspecimenDivulgableRepositoryInterface $repoDivulgable,
    ): array {
        $registros = $this->aplicarVisibilidad($proveedor->buscarPorEspecimenIds($especimenIds), $repoDivulgable);
        // El proveedor hidrata en orden de origen; la página ya trae los UUID
        // ordenados por la columna pública elegida por el usuario.
        $posiciones = array_flip($especimenIds);
        usort($registros, static fn (object $a, object $b): int => $posiciones[$a->especimen_id] <=> $posiciones[$b->especimen_id]);
        return $registros;
    }

    /** @param list<DatosEspecimenProveedor> $datos @return list<object> */
    private function aplicarVisibilidad(array $datos, EspecimenDivulgableRepositoryInterface $repoDivulgable): array
    {
        if ($datos === []) {
            return [];
        }

        // Config de visibilidad indexada por especimenId (FK estable compartida con el DTO).
        $configPorEspecimen = [];
        foreach ($repoDivulgable->buscarPublicadosPorEspecimenIds(array_map(fn (DatosEspecimenProveedor $dto): string => $dto->especimenId, $datos)) as $divulgable) {
            $configPorEspecimen[$divulgable->especimenId()] = $divulgable;
        }

        $datosPublicados = array_values(array_filter($datos, fn (DatosEspecimenProveedor $dto): bool => isset($configPorEspecimen[$dto->especimenId])));

        return array_map(
            function (DatosEspecimenProveedor $dto) use ($configPorEspecimen): object {
                $cfg = $configPorEspecimen[$dto->especimenId];
                $ver = fn (callable $flag): bool => $flag($cfg);
                $g = fn (bool $visible, mixed $valor): mixed => $visible ? $valor : null;

                return (object) [
                    'especimen_id' => $dto->especimenId,
                    'occurrence_id' => $g($ver(fn (EspecimenDivulgable $d) => $d->occurrenceIDVisible()), $dto->occurrenceId),
                    'scientific_name' => $g($ver(fn (EspecimenDivulgable $d) => $d->scientificNameVisible()), $dto->scientificName),
                    ...($cfg->scientificNameVisible() ? [
                        'phylum' => $dto->phylum,
                        'class' => $dto->class,
                        'order' => $dto->order,
                        ...($cfg->familyVisible() ? ['family' => $dto->family] : []),
                        ...($cfg->genusVisible() ? ['genus' => $dto->genus] : []),
                    ] : []),
                    'taxon_en_revision' => $ver(fn (EspecimenDivulgable $d) => $d->scientificNameVisible()) && ($dto->taxonomiaEnRevision || ! CalidadDatoPublico::esTextoValido($dto->scientificName)),
                    'individual_count' => $g($ver(fn (EspecimenDivulgable $d) => $d->individualCountVisible()), $dto->individualCount),
                    'type_status' => $g($ver(fn (EspecimenDivulgable $d) => $d->typeStatusVisible()), $dto->typeStatus),
                    'type_status_visible' => $ver(fn (EspecimenDivulgable $d) => $d->typeStatusVisible()),
                    'disposition' => $g($ver(fn (EspecimenDivulgable $d) => $d->typeStatusVisible()), $dto->disposition),
                    'type_notes' => $g($ver(fn (EspecimenDivulgable $d) => $d->typeNotesVisible()), $dto->typeNotes),
                    'specimen_notes' => $g($ver(fn (EspecimenDivulgable $d) => $d->specimenNotesVisible()), $dto->specimenNotes),
                    'sampling_protocol' => $g($ver(fn (EspecimenDivulgable $d) => $d->samplingProtocolVisible()), $dto->samplingProtocol),
                    'recorded_by' => $g($ver(fn (EspecimenDivulgable $d) => $d->recordedByVisible()), $dto->recordedBy),
                    'occurrence_status' => $g($ver(fn (EspecimenDivulgable $d) => $d->occurrenceStatusVisible()), $dto->occurrenceStatus),
                    'country' => $g($ver(fn (EspecimenDivulgable $d) => $d->countryVisible()), $dto->country),
                    'state_province' => $g($ver(fn (EspecimenDivulgable $d) => $d->stateProvinceVisible()), $dto->stateProvince),
                    'locality_name' => $g($ver(fn (EspecimenDivulgable $d) => $d->localityNameVisible()), $dto->localityName),
                    'locality_excel' => $g($ver(fn (EspecimenDivulgable $d) => $d->localityNameVisible()), $dto->localityExcel),
                    'locality_inec' => $g($ver(fn (EspecimenDivulgable $d) => $d->localityNameVisible()), $dto->localityInec),
                    'locality_inec_reference' => $g($ver(fn (EspecimenDivulgable $d) => $d->localityNameVisible()), $dto->localityInecReference),
                    'locality_visible' => $ver(fn (EspecimenDivulgable $d) => $d->localityNameVisible()),
                    'coordinate_reference' => $g($ver(fn (EspecimenDivulgable $d) => $d->decimalLatitudeVisible()) && $ver(fn (EspecimenDivulgable $d) => $d->decimalLongitudeVisible()), $dto->coordinateReference),
                    'decimal_latitude' => $g($ver(fn (EspecimenDivulgable $d) => $d->decimalLatitudeVisible()), $dto->decimalLatitude),
                    'decimal_longitude' => $g($ver(fn (EspecimenDivulgable $d) => $d->decimalLongitudeVisible()), $dto->decimalLongitude),
                    'elevation_min_m' => $g($ver(fn (EspecimenDivulgable $d) => $d->elevationVisible()), $dto->elevationMinM),
                    'elevation_max_m' => $g($ver(fn (EspecimenDivulgable $d) => $d->elevationVisible()), $dto->elevationMaxM),
                    'event_date' => $g($ver(fn (EspecimenDivulgable $d) => $d->eventDateVisible()), $dto->eventDate),
                    'caste' => $g($ver(fn (EspecimenDivulgable $d) => $d->casteVisible()), $dto->caste),
                    'life_stage' => $g($ver(fn (EspecimenDivulgable $d) => $d->lifeStageVisible()), $dto->lifeStage),
                ];
            },
            $datosPublicados
        );
    }

}
