<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarActaDocumento;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarItemsPrestamo\ItemPrestamoVista;

/**
 * Output DTO con el contenido completo del documento de un acta de préstamo,
 * incluyendo los datos de la solicitud y sus ítems, listo para renderizar.
 */
final readonly class ConsultarActaDocumentoOutput
{
    /**
     * @param string $id Identificador del acta.
     * @param string $investigadorId ID del investigador dueño (para autorización).
     * @param string|null $nombreInvestigador Nombre legible del investigador solicitante.
     * @param string $numeroPrestamo Número de préstamo asignado.
     * @param string $tipoPrestamo Tipo de préstamo.
     * @param string $alcancePrestamo Alcance (nacional/internacional).
     * @param DateTimeImmutable $fechaInicio Fecha de inicio del préstamo.
     * @param DateTimeImmutable $fechaFin Fecha de fin del préstamo.
     * @param string|null $condicionesGenerales Condiciones generales del acta.
     * @param string|null $numeroSolicitud Número de la solicitud asociada.
     * @param string|null $tituloEstudio Título del estudio.
     * @param string|null $institucionAdscripcion Institución de adscripción.
     * @param string|null $lineaInvestigacion Línea de investigación.
     * @param string|null $propositoPrestamo Propósito del préstamo.
     * @param string $patente Patente del laboratorio vigente el año del acta.
     * @param list<ItemPrestamoVista> $items Ítems (especímenes) del préstamo.
     */
    public function __construct(
        public string $id,
        public string $investigadorId,
        public ?string $nombreInvestigador,
        public string $numeroPrestamo,
        public string $tipoPrestamo,
        public string $alcancePrestamo,
        public DateTimeImmutable $fechaInicio,
        public DateTimeImmutable $fechaFin,
        public ?string $condicionesGenerales,
        public ?string $numeroSolicitud,
        public ?string $tituloEstudio,
        public ?string $institucionAdscripcion,
        public ?string $lineaInvestigacion,
        public ?string $propositoPrestamo,
        public string $patente,
        public array $items,
    ) {}
}
