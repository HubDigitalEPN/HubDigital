<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\IniciarRecepcionLote;

use Modules\GestionPrestamosRecepciones\Domain\Entities\RecepcionLote;

/** Output de la iniciación de la recepción: identidad del lote, su estado y el Código QR que lo rotula. */
final readonly class IniciarRecepcionLoteOutput
{
    public function __construct(
        public string $recepcionLoteId,
        public string $estado,
        public string $codigoQR,
    ) {}

    public static function fromEntity(RecepcionLote $lote): self
    {
        return new self(
            recepcionLoteId: (string) $lote->id(),
            estado: $lote->estado()->value,
            codigoQR: (string) $lote->codigoQR(),
        );
    }
}
