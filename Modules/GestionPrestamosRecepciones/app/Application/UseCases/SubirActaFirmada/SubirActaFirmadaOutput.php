<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\SubirActaFirmada;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\Entities\ActaPrestamo;

final readonly class SubirActaFirmadaOutput
{
    public function __construct(
        public string $actaId,
        public string $numeroPrestamo,
        public string $estadoActa,
        public string $pdfFirmadoRuta,
        public ?DateTimeImmutable $firmadaSubidaEn,
    ) {}

    public static function fromPrimitives(ActaPrestamo $acta): self
    {
        return new self(
            actaId:          (string) $acta->id(),
            numeroPrestamo:  (string) $acta->numeroPrestamo(),
            estadoActa:      $acta->estado()->value,
            pdfFirmadoRuta:  $acta->pdfFirmadoRuta(),
            firmadaSubidaEn: $acta->firmadaSubidaEn(),
        );
    }
}
