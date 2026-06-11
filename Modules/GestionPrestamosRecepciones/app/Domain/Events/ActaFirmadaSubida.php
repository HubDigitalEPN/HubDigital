<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Events;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ActaPrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudPrestamoId;

/**
 * Evento de dominio emitido cuando el investigador sube el acta firmada junto con su documento de identidad.
 */
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
