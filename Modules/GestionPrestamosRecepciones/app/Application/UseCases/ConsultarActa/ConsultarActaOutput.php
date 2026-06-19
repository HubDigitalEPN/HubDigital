<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarActa;

use DateTimeImmutable;

/**
 * Output DTO con los datos de un acta de préstamo y de su solicitud asociada,
 * listo para renderizar en la pantalla de validación de actas.
 */
final readonly class ConsultarActaOutput
{
    /**
     * @param string $id Identificador del acta.
     * @param string $numeroPrestamo Número de préstamo asignado.
     * @param string $estado Estado del acta.
     * @param string $tipoPrestamo Tipo de préstamo.
     * @param string $alcancePrestamo Alcance (nacional/internacional).
     * @param DateTimeImmutable $fechaInicio Fecha de inicio del préstamo.
     * @param DateTimeImmutable $fechaFin Fecha de fin del préstamo.
     * @param string|null $pdfFirmadoRuta Ruta del PDF firmado, si existe.
     * @param string|null $documentoIdentidadRuta Ruta del documento de identidad, si existe.
     * @param string|null $documentoExportacionRuta Ruta del documento de exportación, si existe.
     * @param string $solicitudPrestamoId ID de la solicitud asociada.
     * @param string|null $numeroSolicitud Número de la solicitud asociada.
     * @param string|null $tituloEstudio Título del estudio.
     * @param string|null $institucionAdscripcion Institución de adscripción.
     */
    public function __construct(
        public string $id,
        public string $numeroPrestamo,
        public string $estado,
        public string $tipoPrestamo,
        public string $alcancePrestamo,
        public DateTimeImmutable $fechaInicio,
        public DateTimeImmutable $fechaFin,
        public ?string $pdfFirmadoRuta,
        public ?string $documentoIdentidadRuta,
        public ?string $documentoExportacionRuta,
        public string $solicitudPrestamoId,
        public ?string $numeroSolicitud,
        public ?string $tituloEstudio,
        public ?string $institucionAdscripcion,
    ) {}
}
