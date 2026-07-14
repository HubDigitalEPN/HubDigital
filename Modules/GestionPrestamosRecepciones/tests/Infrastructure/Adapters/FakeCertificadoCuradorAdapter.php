<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Adapters;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Application\Ports\CertificadoCurador;
use Modules\GestionPrestamosRecepciones\Application\Ports\CertificadoCuradorPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\DatosCertificado;

/**
 * Custodia de certificado de prueba en memoria. Por defecto devuelve un certificado
 * ficticio; pon el curador en $sinCertificado para simular que no tiene certificado.
 */
final class FakeCertificadoCuradorAdapter implements CertificadoCuradorPort
{
    /** @var array<string, bool> curadorId => true cuando NO debe tener certificado */
    public array $sinCertificado = [];

    public function obtener(string $curadorId): ?CertificadoCurador
    {
        if ($this->sinCertificado[$curadorId] ?? false) {
            return null;
        }

        return new CertificadoCurador(
            contenidoP12: 'p12-ficticio',
            contrasenia: 'clave-ficticia',
        );
    }

    public function guardar(string $curadorId, string $contenidoP12, string $contrasenia): DatosCertificado
    {
        return new DatosCertificado(
            commonName: 'CURADOR DE PRUEBA',
            serial: 'AABBCCDD',
            validoHasta: new DateTimeImmutable('2030-01-01'),
        );
    }
}
