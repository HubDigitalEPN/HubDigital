<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\UseCases\ExportarRegistrosEspecimenes;

use DateTimeImmutable;
use Modules\CatalogoPublico\Application\Ports\DatosEspecimenProveedor;
use Modules\CatalogoPublico\Application\Ports\GeneradorXlsxPort;
use Modules\CatalogoPublico\Application\Ports\ProveedorEspecimenesPort;
use Modules\CatalogoPublico\Domain\Repositories\EspecimenDivulgableRepositoryInterface;
use Modules\CatalogoPublico\Domain\ValueObjects\NombreArchivoExportacion;
use Modules\CatalogoPublico\Domain\ValueObjects\RegistroExportable;

final class ExportarRegistrosEspecimenesHandler
{
    public function __construct(
        private readonly ProveedorEspecimenesPort $proveedorEspecimenes,
        private readonly EspecimenDivulgableRepositoryInterface $repoDivulgable,
        private readonly GeneradorXlsxPort $generadorXlsx,
    ) {}

    public function handle(ExportarRegistrosEspecimenesInput $input): ExportarRegistrosEspecimenesOutput
    {
        $nombreArchivo = NombreArchivoExportacion::generar($input->especieNombre, new DateTimeImmutable);

        $contenido = $this->generadorXlsx->generar(RegistroExportable::encabezados(), $this->filasPublicas($input));
        return ExportarRegistrosEspecimenesOutput::fromPrimitives($nombreArchivo->valor(), $contenido);
    }

    /** Los UUID de la selección se hidratan por lotes y se escriben secuencialmente. */
    private function filasPublicas(ExportarRegistrosEspecimenesInput $input): \Generator
    {
        $lotes = $input->especimenIds === null
            ? [null] : array_chunk($input->especimenIds, 500);
        foreach ($lotes as $ids) {
            $datosEspecimenes = $ids === null ? $this->proveedorEspecimenes->buscarPorNombreCientifico($input->especieNombre)
                : $this->proveedorEspecimenes->buscarPorEspecimenIds($ids);
            $divulgablesPorEspecimenId = [];
            foreach ($this->repoDivulgable->buscarPublicadosPorEspecimenIds(array_map(fn (DatosEspecimenProveedor $d): string => $d->especimenId, $datosEspecimenes)) as $divulgable)
                $divulgablesPorEspecimenId[$divulgable->especimenId()] = $divulgable;
            foreach ($datosEspecimenes as $datoEspecimen) {
                $divulgable = $divulgablesPorEspecimenId[$datoEspecimen->especimenId] ?? null;
                if ($divulgable === null) {
                    continue;
                }

                yield RegistroExportable::desde(
                    occurrenceID: $datoEspecimen->occurrenceId,
                    scientificName: $datoEspecimen->scientificName,
                    typeStatus: $datoEspecimen->typeStatus,
                    occurrenceStatus: $datoEspecimen->occurrenceStatus,
                    individualCount: $datoEspecimen->individualCount,
                    localityName: $datoEspecimen->localityName,
                    country: $datoEspecimen->country,
                    decimalLatitude: $datoEspecimen->decimalLatitude,
                    decimalLongitude: $datoEspecimen->decimalLongitude,
                    recordedBy: $datoEspecimen->recordedBy,
                    samplingProtocol: $datoEspecimen->samplingProtocol,
                    typeNotes: $datoEspecimen->typeNotes,
                    specimenNotes: $datoEspecimen->specimenNotes,
                    stateProvince: $datoEspecimen->stateProvince,
                    elevationMinM: $datoEspecimen->elevationMinM,
                    elevationMaxM: $datoEspecimen->elevationMaxM,
                    eventDate: $datoEspecimen->eventDate,
                    caste: $datoEspecimen->caste,
                    lifeStage: $datoEspecimen->lifeStage,
                    visibilidad: $divulgable->configuracion(),
                    disposition: $datoEspecimen->disposition,
                    georeferenceRemarks: $datoEspecimen->coordinateReference,
                    localityExcel: $datoEspecimen->localityExcel,
                    localityInec: $datoEspecimen->localityInec,
                    localityInecReference: $datoEspecimen->localityInecReference,
                )->toArray();
            }
        }
    }
}
