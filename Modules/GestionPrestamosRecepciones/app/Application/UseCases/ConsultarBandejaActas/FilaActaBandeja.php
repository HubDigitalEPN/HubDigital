<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarBandejaActas;

use DateTimeImmutable;

/**
 * Fila de lectura de la bandeja de actas del curador.
 *
 * {@see ConsultarBandejaActasOutput}
 */
final readonly class FilaActaBandeja
{
    public function __construct(
        public string $actaId,
        public string $numeroPrestamo,
        public ?string $numeroSolicitud,
        public ?string $solicitanteNombre,
        public string $estado,
        public DateTimeImmutable $fecha,
    ) {}
}
