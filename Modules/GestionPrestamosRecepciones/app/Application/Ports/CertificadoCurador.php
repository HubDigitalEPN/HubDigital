<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\Ports;

/**
 * DTO de transporte con el material sensible del certificado de firma del curador:
 * el contenido binario del archivo PKCS#12 (.p12) y su contraseña.
 *
 * Es efímero: se usa en memoria para firmar y nunca debe registrarse en logs ni
 * exponerse. Lo provee {@see CertificadoCuradorPort::obtener()}.
 */
final readonly class CertificadoCurador
{
    public function __construct(
        public string $contenidoP12,
        public string $contrasenia,
    ) {}
}
