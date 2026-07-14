<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarActa;

use Modules\GestionPrestamosRecepciones\Domain\Repositories\ActaPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ActaPrestamoId;

/**
 * Manejador del caso de uso para consultar un acta de préstamo y los datos de su
 * solicitud asociada.
 *
 * {@see ConsultarActaInput}
 * {@see ConsultarActaOutput}
 */
final class ConsultarActaHandler
{
    /**
     * @param  ActaPrestamoRepositoryInterface  $actaRepo  Repositorio de actas de préstamo.
     * @param  SolicitudPrestamoRepositoryInterface  $solicitudRepo  Repositorio de solicitudes de préstamo.
     */
    public function __construct(
        private readonly ActaPrestamoRepositoryInterface $actaRepo,
        private readonly SolicitudPrestamoRepositoryInterface $solicitudRepo,
    ) {}

    /**
     * Ejecuta el caso de uso.
     *
     * @param  ConsultarActaInput  $input  Datos de entrada.
     * @return ConsultarActaOutput|null Datos del acta, o null si no existe.
     */
    public function handle(ConsultarActaInput $input): ?ConsultarActaOutput
    {
        $acta = $this->actaRepo->buscarPorId(ActaPrestamoId::fromString($input->actaId));

        if ($acta === null) {
            return null;
        }

        $solicitud = $this->solicitudRepo->buscarPorId($acta->solicitudPrestamoId());

        $firma = $acta->firmaCuradorMetadata();

        return new ConsultarActaOutput(
            id: (string) $acta->id(),
            numeroPrestamo: (string) $acta->codigoPrestamo(),
            estado: $acta->estado()->value,
            tipoPrestamo: $acta->tipoPrestamo()->value,
            alcancePrestamo: $acta->alcancePrestamo()->value,
            fechaInicio: $acta->fechaInicio(),
            fechaFin: $acta->fechaFin(),
            pdfFirmadoRuta: $acta->pdfFirmadoRuta(),
            documentoIdentidadRuta: $acta->documentoIdentidadRuta(),
            documentoExportacionRuta: $acta->documentoExportacionRuta(),
            solicitudPrestamoId: (string) $acta->solicitudPrestamoId(),
            numeroSolicitud: $solicitud !== null ? (string) $solicitud->codigoPrestamo() : null,
            tituloEstudio: $solicitud?->tituloEstudio(),
            institucionAdscripcion: $solicitud?->institucionAdscripcion(),
            firmadoCuradorCommonName: $firma?->commonName(),
            firmadoCuradorSelloDeTiempo: $firma?->selloDeTiempo(),
        );
    }
}
