<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ReintentarRecepcionLote;

/** Output del reintento: estado del lote reabierto y vigencia del Código QR. */
final readonly class ReintentarRecepcionLoteOutput
{
    public function __construct(
        public string $estado,
        public bool $codigoQrVigente,
    ) {}

    public static function fromPrimitives(string $estado, bool $codigoQrVigente): self
    {
        return new self(estado: $estado, codigoQrVigente: $codigoQrVigente);
    }
}
