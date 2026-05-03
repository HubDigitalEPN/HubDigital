<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ValidarDocumentacionInicial;

use Modules\GestionPrestamosRecepciones\Application\Exceptions\SolicitudNoEncontradaException;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudDepositoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Services\ReglaPermisoMovilizacion;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;

final class ValidarDocumentacionInicialHandler
{
    public function __construct(
        private SolicitudDepositoRepositoryInterface $repo,
        private ReglaPermisoMovilizacion $regla,
    ) {}

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
