<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Domain\Services;

use Modules\CatalogoPublico\Application\Ports\DatosEspecimenProveedor;
use Modules\CatalogoPublico\Application\Ports\ProveedorEspecimenesPort;
use Modules\CatalogoPublico\Domain\Entities\EspecimenDivulgable;
use Modules\CatalogoPublico\Domain\Repositories\EspecimenDivulgableRepositoryInterface;
use Modules\CatalogoPublico\Domain\ValueObjects\DatosEspecimenParaContexto;
use Modules\CatalogoPublico\Domain\ValueObjects\EntidadesExtraidas;

final readonly class RecuperadorContextoEspecimenes
{
    public function __construct(
        private ProveedorEspecimenesPort $proveedorEspecimenes,
        private EspecimenDivulgableRepositoryInterface $repoDivulgable,
    ) {}

    /** @return list<DatosEspecimenParaContexto> */
    public function recuperar(EntidadesExtraidas $entidades): array
    {
        if ($entidades->estaVacio()) {
            return [];
        }

        $candidatos = $this->recuperarCandidatos($entidades);

        if ($candidatos === []) {
            return [];
        }

        $divulgablesPorOccurrenceID = $this->indexarDivulgables($candidatos);

        $resultado = [];

        foreach ($candidatos as $datos) {
            $divulgable = $divulgablesPorOccurrenceID[$datos->occurrenceId] ?? null;

            if ($divulgable === null) {
                continue;
            }

            if (! $divulgable->occurrenceIDVisible()) {
                continue;
            }

            $resultado[] = DatosEspecimenParaContexto::crear(
                occurrenceID: $datos->occurrenceId,
                camposVisibles: $this->filtrarPorVisibilidad($divulgable, $datos),
            );
        }

        return $resultado;
    }

    /** @return list<DatosEspecimenProveedor> */
    private function recuperarCandidatos(EntidadesExtraidas $entidades): array
    {
        if ($entidades->occurrenceID !== null) {
            return $this->proveedorEspecimenes->buscarPorOccurrenceIds([$entidades->occurrenceID]);
        }

        if ($entidades->especie !== null) {
            return $this->proveedorEspecimenes->buscarPorNombreCientifico($entidades->especie);
        }

        return $this->filtrarEnMemoria(
            $this->proveedorEspecimenes->obtenerTodos(),
            $entidades,
        );
    }

    /**
     * @param  list<DatosEspecimenProveedor>  $todos
     * @return list<DatosEspecimenProveedor>
     */
    private function filtrarEnMemoria(array $todos, EntidadesExtraidas $entidades): array
    {
        $genero = $entidades->genero !== null ? mb_strtolower($entidades->genero, 'UTF-8') : null;
        $localidad = $entidades->localidad !== null ? mb_strtolower($entidades->localidad, 'UTF-8') : null;
        $country = $entidades->country !== null ? mb_strtolower($entidades->country, 'UTF-8') : null;

        $filtrados = array_filter($todos, static function (DatosEspecimenProveedor $datos) use ($genero, $localidad, $country): bool {
            if ($genero !== null && ($datos->genus === null || mb_strtolower($datos->genus, 'UTF-8') !== $genero)) {
                return false;
            }

            if ($localidad !== null && ($datos->localityName === null || ! str_contains(mb_strtolower($datos->localityName, 'UTF-8'), $localidad))) {
                return false;
            }

            if ($country !== null && ($datos->country === null || mb_strtolower($datos->country, 'UTF-8') !== $country)) {
                return false;
            }

            return true;
        });

        return array_values($filtrados);
    }

    /**
     * @param  list<DatosEspecimenProveedor>  $candidatos
     * @return array<string, EspecimenDivulgable>
     */
    private function indexarDivulgables(array $candidatos): array
    {
        $occurrenceIDs = array_map(
            static fn (DatosEspecimenProveedor $d): string => $d->occurrenceId,
            $candidatos,
        );

        $divulgables = $this->repoDivulgable->buscarPorOccurrenceIDs($occurrenceIDs);

        $indexados = [];
        foreach ($divulgables as $divulgable) {
            $occurrenceID = $this->occurrenceIDDeDivulgable($divulgable, $candidatos);
            if ($occurrenceID !== null) {
                $indexados[$occurrenceID] = $divulgable;
            }
        }

        return $indexados;
    }

    /** @param list<DatosEspecimenProveedor> $candidatos */
    private function occurrenceIDDeDivulgable(EspecimenDivulgable $divulgable, array $candidatos): ?string
    {
        foreach ($candidatos as $datos) {
            if ($datos->especimenId === $divulgable->especimenId()) {
                return $datos->occurrenceId;
            }
        }

        return null;
    }

    /** @return array<string, scalar|null> */
    private function filtrarPorVisibilidad(EspecimenDivulgable $divulgable, DatosEspecimenProveedor $datos): array
    {
        $result = [];

        if ($divulgable->occurrenceIDVisible()) {
            $result['occurrenceID'] = $datos->occurrenceId;
        }
        if ($divulgable->scientificNameVisible()) {
            $result['scientificName'] = $datos->scientificName;
        }
        if ($divulgable->individualCountVisible()) {
            $result['individualCount'] = $datos->individualCount;
        }
        if ($divulgable->typeStatusVisible()) {
            $result['typeStatus'] = $datos->typeStatus;
        }
        if ($divulgable->typeNotesVisible()) {
            $result['typeNotes'] = $datos->typeNotes;
        }
        if ($divulgable->specimenNotesVisible()) {
            $result['specimenNotes'] = $datos->specimenNotes;
        }
        if ($divulgable->samplingProtocolVisible()) {
            $result['samplingProtocol'] = $datos->samplingProtocol;
        }
        if ($divulgable->recordedByVisible()) {
            $result['recordedBy'] = $datos->recordedBy;
        }
        if ($divulgable->occurrenceStatusVisible()) {
            $result['occurrenceStatus'] = $datos->occurrenceStatus;
        }
        if ($divulgable->familyVisible()) {
            $result['family'] = $datos->family;
        }
        if ($divulgable->genusVisible()) {
            $result['genus'] = $datos->genus;
        }
        if ($divulgable->countryVisible()) {
            $result['country'] = $datos->country;
        }
        if ($divulgable->stateProvinceVisible()) {
            $result['stateProvince'] = $datos->stateProvince;
        }
        if ($divulgable->localityNameVisible()) {
            $result['localityName'] = $datos->localityName;
        }
        if ($divulgable->decimalLatitudeVisible()) {
            $result['decimalLatitude'] = $datos->decimalLatitude;
        }
        if ($divulgable->decimalLongitudeVisible()) {
            $result['decimalLongitude'] = $datos->decimalLongitude;
        }
        if ($divulgable->elevationVisible()) {
            $result['elevationMinM'] = $datos->elevationMinM;
            $result['elevationMaxM'] = $datos->elevationMaxM;
        }
        if ($divulgable->eventDateVisible()) {
            $result['eventDate'] = $datos->eventDate;
        }
        if ($divulgable->casteVisible()) {
            $result['caste'] = $datos->caste;
        }
        if ($divulgable->lifeStageVisible()) {
            $result['lifeStage'] = $datos->lifeStage;
        }

        return $result;
    }
}
