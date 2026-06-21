<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDetalleSolicitud;

use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarItemsPrestamo\ItemPrestamoVista;

/**
 * Output DTO con el detalle completo de una solicitud de préstamo y sus ítems,
 * listo para renderizar en la pantalla de detalle de solicitud.
 */
final readonly class ConsultarDetalleSolicitudOutput
{
    /**
     * @param string $solicitudId ID de la solicitud.
     * @param string $investigadorId ID del investigador dueño (para autorización).
     * @param string $numeroSolicitud Número de la solicitud.
     * @param string $estado Estado de la solicitud.
     * @param string|null $tituloEstudio Título del estudio.
     * @param string|null $institucionAdscripcion Institución de adscripción.
     * @param string|null $lineaInvestigacion Línea de investigación.
     * @param int|null $duracionPropuestaMeses Duración propuesta en meses.
     * @param string|null $alcancePrestamo Alcance del préstamo.
     * @param string|null $propositoPrestamo Propósito del préstamo.
     * @param string|null $justificacionExtendida Justificación de duración extendida.
     * @param string|null $comentarioCurador Comentario del curador.
     * @param list<ItemPrestamoVista> $items Ítems (especímenes) solicitados.
     */
    public function __construct(
        public string $solicitudId,
        public string $investigadorId,
        public string $numeroSolicitud,
        public string $estado,
        public ?string $tituloEstudio,
        public ?string $institucionAdscripcion,
        public ?string $lineaInvestigacion,
        public ?int $duracionPropuestaMeses,
        public ?string $alcancePrestamo,
        public ?string $propositoPrestamo,
        public ?string $justificacionExtendida,
        public ?string $comentarioCurador,
        public array $items,
    ) {}
}
