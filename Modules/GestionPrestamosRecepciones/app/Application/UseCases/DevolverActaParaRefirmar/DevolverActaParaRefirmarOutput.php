<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\DevolverActaParaRefirmar;

use Modules\GestionPrestamosRecepciones\Domain\Entities\ActaPrestamo;

final readonly class DevolverActaParaRefirmarOutput
{
    public function __construct(
        public string $actaId,
        public string $numeroPrestamo,
        public string $estadoActa,
    ) {}

    public static function fromPrimitives(ActaPrestamo $acta): self
    {
        return new self(
            actaId: (string) $acta->id(),
            numeroPrestamo: (string) $acta->numeroPrestamo(),
            estadoActa: $acta->estado()->value,
        );
    }
}
