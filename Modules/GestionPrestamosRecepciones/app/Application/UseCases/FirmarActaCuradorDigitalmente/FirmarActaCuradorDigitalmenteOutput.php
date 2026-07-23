<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\FirmarActaCuradorDigitalmente;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\Entities\ActaPrestamo;

/**
 * Datos de salida tras la firma canvas del acta por el curador.
 */
final readonly class FirmarActaCuradorDigitalmenteOutput
{
    public function __construct(
        public string $actaId,
        public string $numeroPrestamo,
        public string $estadoActa,
        public ?string $pdfFirmadoCuradorRuta,
        public ?DateTimeImmutable $validadaEn,
    ) {}

    public static function fromPrimitives(ActaPrestamo $acta): self
    {
        return new self(
            actaId: (string) $acta->id(),
            numeroPrestamo: (string) $acta->codigoPrestamo(),
            estadoActa: $acta->estado()->value,
            pdfFirmadoCuradorRuta: $acta->pdfFirmadoCuradorRuta(),
            validadaEn: $acta->validadaEn(),
        );
    }
}
