<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\RegistrarCertificadoCurador;

/**
 * Datos de entrada para registrar (o reemplazar) el certificado de firma del curador.
 */
final readonly class RegistrarCertificadoCuradorInput
{
    public function __construct(
        public string $curadorId,
        public string $contenidoP12,
        public string $contrasenia,
    ) {}
}
