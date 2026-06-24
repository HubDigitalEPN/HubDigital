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

        $datosEspecimenes = $this->proveedorEspecimenes->buscarPorNombreCientifico($input->especieNombre);

        if ($datosEspecimenes === []) {
            $contenido = $this->generadorXlsx->generar(RegistroExportable::encabezados(), []);

            return ExportarRegistrosEspecimenesOutput::fromPrimitives($nombreArchivo->valor(), $contenido);
        }

        $occurrenceIDs = array_map(fn (DatosEspecimenProveedor $d) => $d->occurrenceId, $datosEspecimenes);

        $divulgables = $this->repoDivulgable->buscarPorOccurrenceIDs($occurrenceIDs);

        $divulgablesPorEspecimenId = [];
        foreach ($divulgables as $divulgable) {
            $divulgablesPorEspecimenId[$divulgable->especimenId()] = $divulgable;
        }

        $registros = [];
        foreach ($datosEspecimenes as $datoEspecimen) {
            $divulgable = $divulgablesPorEspecimenId[$datoEspecimen->especimenId] ?? null;
            if ($divulgable === null) {
                continue;
            }

            $registros[] = RegistroExportable::desde(
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
            );
        }

        $filas = array_map(fn (RegistroExportable $r) => $r->toArray(), $registros);
        $contenido = $this->generadorXlsx->generar(RegistroExportable::encabezados(), $filas);

        return ExportarRegistrosEspecimenesOutput::fromPrimitives($nombreArchivo->valor(), $contenido);
    }
}
