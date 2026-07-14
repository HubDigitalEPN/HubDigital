<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Adapters;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Application\Ports\CertificadoCurador;
use Modules\GestionPrestamosRecepciones\Application\Ports\FirmadorPdfPort;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\FirmaCuradorMetadata;

/**
 * Firmador de prueba: no ejecuta pyHanko ni toca el sistema de archivos. Registra la
 * llamada y devuelve una evidencia de firma fija.
 */
final class FakeFirmadorPdfAdapter implements FirmadorPdfPort
{
    /** @var list<array{entrada: string, salida: string, etiqueta: string}> */
    public array $firmas = [];

    public function firmar(
        string $rutaPdfEntrada,
        CertificadoCurador $certificado,
        string $rutaPdfSalida,
        string $etiqueta,
    ): FirmaCuradorMetadata {
        $this->firmas[] = [
            'entrada' => $rutaPdfEntrada,
            'salida' => $rutaPdfSalida,
            'etiqueta' => $etiqueta,
        ];

        return FirmaCuradorMetadata::crear(
            commonName: 'CURADOR DE PRUEBA',
            serialCertificado: 'AABBCCDD',
            huella: 'sha256:fake',
            algoritmo: 'SHA256withRSA',
            selloDeTiempo: new DateTimeImmutable('2026-07-02 10:00:00'),
        );
    }
}
