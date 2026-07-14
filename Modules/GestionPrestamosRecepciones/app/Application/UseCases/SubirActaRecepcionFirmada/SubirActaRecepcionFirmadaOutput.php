<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\SubirActaRecepcionFirmada;

use Modules\GestionPrestamosRecepciones\Domain\Entities\RecepcionLote;

/** Resultado de adjuntar el acta de recepción firmada. */
final readonly class SubirActaRecepcionFirmadaOutput
{
    public function __construct(
        public string $solicitudId,
        public bool $actaFirmada,
        public ?string $firmadaEn,
    ) {}

    public static function fromEntity(RecepcionLote $lote): self
    {
        return new self(
            solicitudId: (string) $lote->solicitudId(),
            actaFirmada: $lote->actaFirmada(),
            firmadaEn: $lote->firmadaEn()?->format('Y-m-d H:i:s'),
        );
    }
}
