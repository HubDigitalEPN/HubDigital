<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\DeterminarDocumentacionRequerida;

use Modules\GestionPrestamosRecepciones\Application\Exceptions\SolicitudNoEncontradaException;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudDepositoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Services\ReglaDocumentacionRequerida;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;

final class DeterminarDocumentacionRequeridaHandler
{
    public function __construct(
        private SolicitudDepositoRepositoryInterface $repo,
        private ReglaDocumentacionRequerida $regla,
    ) {}

    public function __invoke(DeterminarDocumentacionRequeridaInput $input): DeterminarDocumentacionRequeridaOutput
    {
        $id = SolicitudDepositoId::from($input->solicitudId);
        $solicitud = $this->repo->buscarPorId($id);

        if ($solicitud === null) {
            throw SolicitudNoEncontradaException::conId($input->solicitudId);
        }

        if ($solicitud->origenRecoleccion() === null || $solicitud->situacionRegulatoria() === null) {
            throw new \DomainException('La solicitud no tiene declarado el origen de recolección y/o la situación regulatoria');
        }

        $documentosRequeridos = $this->regla->determinar(
            $solicitud->origenRecoleccion(),
            $solicitud->situacionRegulatoria()
        );

        return new DeterminarDocumentacionRequeridaOutput(
            documentosRequeridos: $documentosRequeridos,
        );
    }
}
