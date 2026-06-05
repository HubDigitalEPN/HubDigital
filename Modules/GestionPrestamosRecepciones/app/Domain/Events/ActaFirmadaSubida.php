<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Events;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ActaPrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudPrestamoId;

final readonly class ActaFirmadaSubida
{
    public function __construct(
        public ActaPrestamoId $actaId,
        public SolicitudPrestamoId $solicitudId,
        public string $pdfFirmadoRuta,
        public string $documentoIdentidadRuta,
        public DateTimeImmutable $ocurridoEn,
    ) {}
}
