<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\Ports;

use DateTimeImmutable;

/**
 * DTO con los datos públicos de un certificado de firma (sin clave ni contraseña),
 * extraídos del PKCS#12 al registrarlo. Sirve para mostrar al curador qué certificado
 * tiene configurado y hasta cuándo es válido.
 */
final readonly class DatosCertificado
{
    public function __construct(
        public string $commonName,
        public string $serial,
        public DateTimeImmutable $validoHasta,
    ) {}
}
