<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ValidarIdentidadSolicitud;

use Modules\GestionPrestamosRecepciones\Application\Exceptions\SolicitudNoEncontradaException;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudDepositoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Services\ReglaValidacionIdentidad;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;

final class ValidarIdentidadSolicitudHandler
{
    public function __construct(
        private SolicitudDepositoRepositoryInterface $repo,
        private ReglaValidacionIdentidad $regla,
    ) {}

    public function __invoke(ValidarIdentidadSolicitudInput $input): ValidarIdentidadSolicitudOutput
    {
        $id = SolicitudDepositoId::from($input->solicitudId);
        $solicitud = $this->repo->buscarPorId($id);

        if ($solicitud === null) {
            throw SolicitudNoEncontradaException::conId($input->solicitudId);
        }

        $resultado = $this->regla->comparar($input->nombrePerfil, $input->nombreEnDocumento);
        $accionesPermitidas = $this->regla->accionesPermitidas($resultado);

        return new ValidarIdentidadSolicitudOutput(
            resultado: $resultado,
            accionesPermitidas: $accionesPermitidas,
        );
    }
}
