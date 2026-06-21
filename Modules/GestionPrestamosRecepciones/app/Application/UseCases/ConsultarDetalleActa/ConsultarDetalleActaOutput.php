<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDetalleActa;

use DateTimeImmutable;

/**
 * Output DTO con el detalle de un acta de préstamo y el estado de su préstamo
 * asociado, listo para renderizar en la pantalla de detalle de acta del investigador.
 */
final readonly class ConsultarDetalleActaOutput
{
    /**
     * @param string $id Identificador del acta.
     * @param string $investigadorId ID del investigador dueño (para autorización).
     * @param string $solicitudPrestamoId ID de la solicitud (para acciones de firma/subida).
     * @param string $numeroPrestamo Número de préstamo.
     * @param string $estado Estado del acta.
     * @param string $tipoPrestamo Tipo de préstamo.
     * @param DateTimeImmutable $fechaInicio Fecha de inicio.
     * @param DateTimeImmutable $fechaFin Fecha de fin.
     * @param string|null $condicionesGenerales Condiciones generales del acta.
     * @param string|null $motivoDevolucion Motivo de devolución, si fue devuelta.
     * @param string $pdfRuta Ruta del PDF original.
     * @param string|null $pdfFirmadoRuta Ruta del PDF/imagen firmada.
     * @param string|null $documentoIdentidadRuta Ruta del documento de identidad.
     * @param string|null $documentoExportacionRuta Ruta del documento de exportación.
     * @param string|null $prestamoId ID del préstamo asociado, si existe.
     * @param string|null $prestamoEstado Estado del préstamo asociado, si existe.
     */
    public function __construct(
        public string $id,
        public string $investigadorId,
        public string $solicitudPrestamoId,
        public string $numeroPrestamo,
        public string $estado,
        public string $tipoPrestamo,
        public DateTimeImmutable $fechaInicio,
        public DateTimeImmutable $fechaFin,
        public ?string $condicionesGenerales,
        public ?string $motivoDevolucion,
        public string $pdfRuta,
        public ?string $pdfFirmadoRuta,
        public ?string $documentoIdentidadRuta,
        public ?string $documentoExportacionRuta,
        public ?string $prestamoId,
        public ?string $prestamoEstado,
    ) {}
}
