<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\FirmarActaDigitalmente;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\Entities\ActaPrestamo;

/**
 * Datos de salida tras firmar digitalmente un acta.
 */
final readonly class FirmarActaDigitalmenteOutput
{
    public function __construct(
        public string $actaId,
        public string $numeroPrestamo,
        public string $estadoActa,
        public string $pdfFirmadoRuta,
        public ?DateTimeImmutable $firmadaSubidaEn,
    ) {}

    /**
     * @param ActaPrestamo $acta
     * @return self
     */
    public static function fromPrimitives(ActaPrestamo $acta): self
    {
        return new self(
            actaId: (string) $acta->id(),
            numeroPrestamo: (string) $acta->codigoPrestamo(),
            estadoActa: $acta->estado()->value,
            pdfFirmadoRuta: $acta->pdfFirmadoRuta() ?? '',
            firmadaSubidaEn: $acta->firmadaSubidaEn(),
        );
    }
}
