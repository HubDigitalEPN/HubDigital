<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\ValueObjects;

/** Evidencia criptografica y de integridad de un PDF firmado. */
final readonly class DetalleValidacionFirma
{
    /** @param array<string, string|bool|null> $certificado */
    public function __construct(
        public ResultadoValidacionFirma $resultado,
        public bool $integridadCriptografica,
        public bool $documentoCompletoFirmado,
        public bool $contenidoOficialCoincide,
        public bool $certificadoVigente,
        public bool $certificadoConfiable,
        public array $certificado = [],
        public ?string $error = null,
        public bool $aceptadaPorMotor = false,
        public bool $formatoFirmaAceptado = false,
    ) {}

    public function esAceptable(): bool
    {
        return $this->aceptadaPorMotor;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'resultado' => $this->resultado->value,
            'integridad_criptografica' => $this->integridadCriptografica,
            'documento_completo_firmado' => $this->documentoCompletoFirmado,
            'contenido_oficial_coincide' => $this->contenidoOficialCoincide,
            'certificado_vigente' => $this->certificadoVigente,
            'certificado_confiable' => $this->certificadoConfiable,
            'formato_firma_aceptado' => $this->formatoFirmaAceptado,
            'aceptable' => $this->aceptadaPorMotor,
            'certificado' => $this->certificado,
            'error' => $this->error,
            'motor' => 'java-pdfbox-bouncycastle',
            'verificado_en' => now()->toIso8601String(),
        ];
    }
}
