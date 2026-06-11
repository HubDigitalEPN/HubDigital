<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ValidarDocumentacionInicial;

use Modules\GestionPrestamosRecepciones\Application\Exceptions\SolicitudNoEncontradaException;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudDepositoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Services\ReglaPermisoMovilizacion;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;

/**
 * Valida la documentación inicial de una solicitud de depósito.
 *
 * {@see ValidarDocumentacionInicialInput}
 * {@see ValidarDocumentacionInicialOutput}
 */
final class ValidarDocumentacionInicialHandler
{
    public function __construct(
        private SolicitudDepositoRepositoryInterface $repo,
        private ReglaPermisoMovilizacion $regla,
    ) {}

    /**
     * @param ValidarDocumentacionInicialInput $input
     * @return ValidarDocumentacionInicialOutput
     * @throws SolicitudNoEncontradaException
     */
    public function __invoke(ValidarDocumentacionInicialInput $input): ValidarDocumentacionInicialOutput
    {
        $id = SolicitudDepositoId::from($input->solicitudId);
        $solicitud = $this->repo->buscarPorId($id);

        if ($solicitud === null) {
            throw SolicitudNoEncontradaException::conId($input->solicitudId);
        }

        $estadoDocumental = $this->regla->validar(
            provinciaOrigen: $input->provinciaOrigen,
            nombresDocumentosAdjuntos: array_keys($input->documentosAdjuntos),
        );

        return new ValidarDocumentacionInicialOutput(
            estadoDocumental: $estadoDocumental,
        );
    }
}
