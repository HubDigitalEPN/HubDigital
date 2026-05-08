<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ValidarActaFirmada;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\Entities\ActaPrestamo;

final readonly class ValidarActaFirmadaOutput
{
    public function __construct(
        public string $actaId,
        public string $numeroPrestamo,
        public string $estadoActa,
        public ?string $validadaPor,
        public ?DateTimeImmutable $validadaEn,
    ) {}

    public static function fromPrimitives(ActaPrestamo $acta): self
    {
        return new self(
            actaId: (string) $acta->id(),
            numeroPrestamo: (string) $acta->numeroPrestamo(),
            estadoActa: $acta->estado()->value,
            validadaPor: $acta->validadaPor(),
            validadaEn: $acta->validadaEn(),
        );
    }
}
