<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\EnviarActaPrestamo;

use Modules\GestionPrestamosRecepciones\Domain\Entities\ActaPrestamo;

final readonly class EnviarActaPrestamoOutput
{
    public function __construct(
        public string $actaId,
        public string $numeroPrestamo,
        public string $estadoActa,
        public string $pdfRuta,
        public bool $notificacionEnviada,
    ) {}

    public static function fromPrimitives(ActaPrestamo $acta, bool $notificacionEnviada): self
    {
        return new self(
            actaId: (string) $acta->id(),
            numeroPrestamo: (string) $acta->numeroPrestamo(),
            estadoActa: $acta->estado()->value,
            pdfRuta: $acta->pdfRuta(),
            notificacionEnviada: $notificacionEnviada,
        );
    }
}
