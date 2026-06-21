<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDetalleActa;

use Modules\GestionPrestamosRecepciones\Domain\Repositories\ActaPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\PrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ActaPrestamoId;

/**
 * Manejador del caso de uso para consultar el detalle de un acta de préstamo junto
 * con el estado de su préstamo asociado.
 *
 * {@see ConsultarDetalleActaInput}
 * {@see ConsultarDetalleActaOutput}
 */
final class ConsultarDetalleActaHandler
{
    /**
     * @param ActaPrestamoRepositoryInterface $actaRepo Repositorio de actas de préstamo.
     * @param SolicitudPrestamoRepositoryInterface $solicitudRepo Repositorio de solicitudes (dueño).
     * @param PrestamoRepositoryInterface $prestamoRepo Repositorio de préstamos (estado asociado).
     */
    public function __construct(
        private readonly ActaPrestamoRepositoryInterface $actaRepo,
        private readonly SolicitudPrestamoRepositoryInterface $solicitudRepo,
        private readonly PrestamoRepositoryInterface $prestamoRepo,
    ) {}

    /**
     * Ejecuta el caso de uso.
     *
     * @param ConsultarDetalleActaInput $input Datos de entrada.
     * @return ConsultarDetalleActaOutput|null Detalle del acta, o null si no existe.
     */
    public function handle(ConsultarDetalleActaInput $input): ?ConsultarDetalleActaOutput
    {
        $actaId = ActaPrestamoId::fromString($input->actaId);

        $acta = $this->actaRepo->buscarPorId($actaId);

        if ($acta === null) {
            return null;
        }

        $solicitud = $this->solicitudRepo->buscarPorId($acta->solicitudPrestamoId());
        $prestamo = $this->prestamoRepo->buscarPorActaId($actaId);

        return new ConsultarDetalleActaOutput(
            id: (string) $acta->id(),
            investigadorId: $solicitud?->investigadorId() ?? '',
            solicitudPrestamoId: (string) $acta->solicitudPrestamoId(),
            numeroPrestamo: (string) $acta->numeroPrestamo(),
            estado: $acta->estado()->value,
            tipoPrestamo: $acta->tipoPrestamo()->value,
            fechaInicio: $acta->fechaInicio(),
            fechaFin: $acta->fechaFin(),
            condicionesGenerales: $acta->condicionesGenerales(),
            motivoDevolucion: $acta->motivoDevolucion(),
            pdfRuta: $acta->pdfRuta(),
            pdfFirmadoRuta: $acta->pdfFirmadoRuta(),
            documentoIdentidadRuta: $acta->documentoIdentidadRuta(),
            documentoExportacionRuta: $acta->documentoExportacionRuta(),
            prestamoId: $prestamo !== null ? (string) $prestamo->id() : null,
            prestamoEstado: $prestamo?->estado()->value,
        );
    }
}
