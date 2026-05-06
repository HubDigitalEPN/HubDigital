<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\GenerarActaPrestamo;

use Modules\GestionPrestamosRecepciones\Domain\Entities\ActaPrestamo;

final readonly class GenerarActaPrestamoOutput
{
    public function __construct(
        public string $solicitudId,
        public string $pdfRuta,
        public bool $notificacionEnviada,
        public ?string $pdfFirmadoRuta,
    ) {}

    public static function fromPrimitives(
        string $solicitudId,
        ActaPrestamo $acta,
        bool $notificacionEnviada,
    ): self {
        return new self(
            solicitudId: $solicitudId,
            pdfRuta: $acta->pdfRuta(),
            notificacionEnviada: $notificacionEnviada,
            pdfFirmadoRuta: $acta->pdfFirmadoRuta(),
        );
    }
}
