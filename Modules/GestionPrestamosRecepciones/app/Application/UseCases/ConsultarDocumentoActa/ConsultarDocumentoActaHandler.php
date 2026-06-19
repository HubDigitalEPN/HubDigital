<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDocumentoActa;

use Modules\GestionPrestamosRecepciones\Domain\Repositories\ActaPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ActaPrestamoId;

/**
 * Manejador del caso de uso para consultar los documentos de un acta de préstamo
 * y autorizar su acceso (curador o investigador dueño de la solicitud).
 *
 * {@see ConsultarDocumentoActaInput}
 * {@see ConsultarDocumentoActaOutput}
 */
final class ConsultarDocumentoActaHandler
{
    /**
     * @param ActaPrestamoRepositoryInterface $actaRepo Repositorio de actas de préstamo.
     * @param SolicitudPrestamoRepositoryInterface $solicitudRepo Repositorio de solicitudes (dueño).
     */
    public function __construct(
        private readonly ActaPrestamoRepositoryInterface $actaRepo,
        private readonly SolicitudPrestamoRepositoryInterface $solicitudRepo,
    ) {}

    /**
     * Ejecuta el caso de uso.
     *
     * @param ConsultarDocumentoActaInput $input Datos de entrada.
     * @return ConsultarDocumentoActaOutput Rutas de documentos y decisión de acceso.
     */
    public function handle(ConsultarDocumentoActaInput $input): ConsultarDocumentoActaOutput
    {
        $acta = $this->actaRepo->buscarPorId(ActaPrestamoId::fromString($input->actaId));

        if ($acta === null) {
            return new ConsultarDocumentoActaOutput(
                existe: false,
                autorizado: false,
                pdfRuta: null,
                pdfFirmadoRuta: null,
                documentoIdentidadRuta: null,
                documentoExportacionRuta: null,
            );
        }

        $solicitud = $this->solicitudRepo->buscarPorId($acta->solicitudPrestamoId());

        $esDueno = $solicitud !== null && $solicitud->investigadorId() === $input->usuarioId;

        return new ConsultarDocumentoActaOutput(
            existe: true,
            autorizado: $input->esCurador || $esDueno,
            pdfRuta: $acta->pdfRuta(),
            pdfFirmadoRuta: $acta->pdfFirmadoRuta(),
            documentoIdentidadRuta: $acta->documentoIdentidadRuta(),
            documentoExportacionRuta: $acta->documentoExportacionRuta(),
        );
    }
}
