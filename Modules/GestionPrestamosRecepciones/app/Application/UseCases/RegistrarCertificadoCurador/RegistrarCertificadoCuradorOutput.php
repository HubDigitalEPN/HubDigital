<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\RegistrarCertificadoCurador;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Application\Ports\DatosCertificado;

/**
 * Datos de salida tras registrar el certificado de firma del curador.
 */
final readonly class RegistrarCertificadoCuradorOutput
{
    public function __construct(
        public string $commonName,
        public string $serial,
        public DateTimeImmutable $validoHasta,
    ) {}

    public static function desde(DatosCertificado $datos): self
    {
        return new self(
            commonName: $datos->commonName,
            serial: $datos->serial,
            validoHasta: $datos->validoHasta,
        );
    }
}
