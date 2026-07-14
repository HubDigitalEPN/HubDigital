<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\RegistrarCertificadoCurador;

use Modules\GestionPrestamosRecepciones\Application\Ports\CertificadoCuradorPort;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\CertificadoCuradorInvalido;

/**
 * Registra (o reemplaza) el certificado de firma electrónica del curador, validándolo
 * y almacenándolo cifrado para poder firmar actas automáticamente al aprobarlas.
 *
 * {@see RegistrarCertificadoCuradorInput}
 * {@see RegistrarCertificadoCuradorOutput}
 */
final class RegistrarCertificadoCuradorHandler
{
    public function __construct(
        private readonly CertificadoCuradorPort $certificados,
    ) {}

    /**
     * @throws CertificadoCuradorInvalido
     */
    public function handle(RegistrarCertificadoCuradorInput $input): RegistrarCertificadoCuradorOutput
    {
        $datos = $this->certificados->guardar(
            $input->curadorId,
            $input->contenidoP12,
            $input->contrasenia,
        );

        return RegistrarCertificadoCuradorOutput::desde($datos);
    }
}
