<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Events;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ActaPrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudPrestamoId;

/**
 * Evento de dominio emitido cuando el curador adjunta su firma al acta (dibujada en
 * canvas o mediante la carga de un PDF firmado) y con ello la valida.
 */
final readonly class ActaFirmadaPorCurador
{
    public function __construct(
        public ActaPrestamoId $actaId,
        public SolicitudPrestamoId $solicitudId,
        public string $curadorId,
        public string $pdfFirmadoCuradorRuta,
        public DateTimeImmutable $ocurridoEn,
    ) {}
}
