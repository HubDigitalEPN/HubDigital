<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\UseCases\ConsultarInformacionDivulgada;

use Modules\CatalogoPublico\Application\Ports\DatosEspecimenProveedor;
use Modules\CatalogoPublico\Application\Ports\ProveedorEspecimenesPort;
use Modules\CatalogoPublico\Domain\Entities\EspecimenDivulgable;
use Modules\CatalogoPublico\Domain\Repositories\EspecimenDivulgableRepositoryInterface;
use RuntimeException;

final class ConsultarInformacionDivulgadaHandler
{
    public function __construct(
        private readonly ProveedorEspecimenesPort $proveedorEspecimenes,
        private readonly EspecimenDivulgableRepositoryInterface $repoDivulgable,
    ) {}

    public function handle(ConsultarInformacionDivulgadaInput $input): ConsultarInformacionDivulgadaOutput
    {
        $divulgable = $this->repoDivulgable->buscarPorOccurrenceID($input->occurrenceID);

        if ($divulgable === null) {
            throw new RuntimeException(
                "El espécimen '{$input->occurrenceID}' no está sincronizado para divulgación"
            );
        }

        $datos = $this->proveedorEspecimenes->buscarPorOccurrenceId($input->occurrenceID);

        if ($datos === null) {
            throw new RuntimeException(
                "El espécimen '{$input->occurrenceID}' no existe en la base interna"
            );
        }

        return ConsultarInformacionDivulgadaOutput::fromPrimitives(
            $this->filtrarPorVisibilidad($divulgable, $datos)
        );
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
        if ($divulgable->localityNameVisible()) {
            $result['localityName'] = $datos->localityName;
        }
        if ($divulgable->decimalLatitudeVisible()) {
            $result['decimalLatitude'] = $datos->decimalLatitude;
        }
        if ($divulgable->decimalLongitudeVisible()) {
            $result['decimalLongitude'] = $datos->decimalLongitude;
        }

        return $result;
    }
}
