<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\ValueObjects;

use DateTimeImmutable;
use InvalidArgumentException;
use Modules\GestionPrestamosRecepciones\Domain\Entities\ActaPrestamo;

/**
 * Value object inmutable con los datos verificables de la firma criptográfica que
 * el curador aplica al acta al aprobarla (PAdES sobre el PDF).
 *
 * No contiene la clave ni el certificado: solo la evidencia que se muestra y se
 * conserva (nombre común del firmante, serial y huella del certificado, algoritmo
 * y sello de tiempo TSA cuando existe). Lo produce el adaptador de firma en
 * infraestructura tras firmar; la entidad {@see ActaPrestamo}
 * lo guarda como evidencia. Construir vía {@see self::crear()}.
 */
final readonly class FirmaCuradorMetadata
{
    private function __construct(
        private string $commonName,
        private string $serialCertificado,
        private string $huella,
        private string $algoritmo,
        private ?DateTimeImmutable $selloDeTiempo,
    ) {}

    /**
     * @param  string  $commonName  Nombre común (CN) del certificado del curador.
     * @param  string  $serialCertificado  Número de serie del certificado.
     * @param  string  $huella  Huella digital del certificado (p. ej. SHA-256).
     * @param  string  $algoritmo  Algoritmo de firma (p. ej. SHA256withRSA).
     * @param  DateTimeImmutable|null  $selloDeTiempo  Sello de tiempo TSA, null si la firma es PAdES-B-B sin TSA.
     *
     * @throws InvalidArgumentException Si algún dato obligatorio está vacío.
     */
    public static function crear(
        string $commonName,
        string $serialCertificado,
        string $huella,
        string $algoritmo,
        ?DateTimeImmutable $selloDeTiempo = null,
    ): self {
        foreach (['commonName' => $commonName, 'serialCertificado' => $serialCertificado, 'huella' => $huella, 'algoritmo' => $algoritmo] as $campo => $valor) {
            if (trim($valor) === '') {
                throw new InvalidArgumentException("El campo '{$campo}' de la firma del curador no puede estar vacío.");
            }
        }

        return new self(
            commonName: trim($commonName),
            serialCertificado: trim($serialCertificado),
            huella: trim($huella),
            algoritmo: trim($algoritmo),
            selloDeTiempo: $selloDeTiempo,
        );
    }

    public function commonName(): string
    {
        return $this->commonName;
    }

    public function serialCertificado(): string
    {
        return $this->serialCertificado;
    }

    public function huella(): string
    {
        return $this->huella;
    }

    public function algoritmo(): string
    {
        return $this->algoritmo;
    }

    public function selloDeTiempo(): ?DateTimeImmutable
    {
        return $this->selloDeTiempo;
    }
}
