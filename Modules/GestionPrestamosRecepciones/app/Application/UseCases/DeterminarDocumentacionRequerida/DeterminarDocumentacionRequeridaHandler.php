<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\DeterminarDocumentacionRequerida;

use Modules\GestionPrestamosRecepciones\Application\Exceptions\SolicitudNoEncontradaException;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudDepositoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Services\ReglaDocumentacionRequerida;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;

/**
 * Manejador del caso de uso para determinar la documentación requerida para una solicitud.
 * 
 * {@see DeterminarDocumentacionRequeridaInput}
 * {@see DeterminarDocumentacionRequeridaOutput}
 */
final class DeterminarDocumentacionRequeridaHandler
{
    /**
     * @param SolicitudDepositoRepositoryInterface $repo Repositorio de solicitudes de depósito.
     * @param ReglaDocumentacionRequerida $regla Servicio de dominio con las reglas de documentación.
     */
    public function __construct(
        private SolicitudDepositoRepositoryInterface $repo,
        private ReglaDocumentacionRequerida $regla,
    ) {}

    /**
     * Ejecuta el caso de uso.
     *
     * @param DeterminarDocumentacionRequeridaInput $input Datos de entrada.
     * @return DeterminarDocumentacionRequeridaOutput Documentos requeridos.
     * @throws \DomainException Si faltan datos o la solicitud no tiene la información necesaria.
     */
    public function __invoke(DeterminarDocumentacionRequeridaInput $input): DeterminarDocumentacionRequeridaOutput
    {
        if ($input->solicitudId !== null) {
            return $this->determinarDesdeSolicitud($input->solicitudId);
        }

        if ($input->tipoTramite === null || $input->origenRecoleccion === null || $input->situacionRegulatoria === null) {
            throw new \DomainException('Se requiere solicitudId o los datos de tipo de trámite, origen y situación regulatoria');
        }

        return new DeterminarDocumentacionRequeridaOutput(
            documentosRequeridos: $this->regla->determinar(
                $input->tipoTramite,
                $input->origenRecoleccion,
                $input->situacionRegulatoria,
                $input->provinciaOrigen,
            ),
        );
    }

    private function determinarDesdeSolicitud(string $solicitudId): DeterminarDocumentacionRequeridaOutput
    {
        $id = SolicitudDepositoId::from($solicitudId);
        $solicitud = $this->repo->buscarPorId($id);

        if ($solicitud === null) {
            throw SolicitudNoEncontradaException::conId($solicitudId);
        }

        if ($solicitud->origenRecoleccion() === null || $solicitud->situacionRegulatoria() === null) {
            throw new \DomainException('La solicitud no tiene declarado el origen de recolección y/o la situación regulatoria');
        }

        return new DeterminarDocumentacionRequeridaOutput(
            documentosRequeridos: $this->regla->determinar(
                $solicitud->tipoTramite(),
                $solicitud->origenRecoleccion(),
                $solicitud->situacionRegulatoria(),
                $solicitud->provinciaOrigen(),
            ),
        );
    }
}
