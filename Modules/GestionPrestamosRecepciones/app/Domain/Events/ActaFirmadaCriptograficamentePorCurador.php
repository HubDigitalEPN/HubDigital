<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Events;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ActaPrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudPrestamoId;

/**
 * Evento de dominio emitido cuando el curador aprueba el acta y ésta queda firmada
 * criptográficamente (PAdES) de forma automática en el servidor con su certificado.
 */
final readonly class ActaFirmadaCriptograficamentePorCurador
{
    public function __construct(
        public ActaPrestamoId $actaId,
        public SolicitudPrestamoId $solicitudId,
        public string $curadorId,
        public string $pdfFirmadoCuradorRuta,
        public string $commonName,
        public DateTimeImmutable $ocurridoEn,
    ) {}
}
