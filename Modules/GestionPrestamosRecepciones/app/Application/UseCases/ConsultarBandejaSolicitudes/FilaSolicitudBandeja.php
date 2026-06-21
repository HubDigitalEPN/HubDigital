<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarBandejaSolicitudes;

use DateTimeImmutable;

/**
 * Fila de lectura de la bandeja de solicitudes del curador.
 *
 * {@see ConsultarBandejaSolicitudesOutput}
 */
final readonly class FilaSolicitudBandeja
{
    public function __construct(
        public string $solicitudId,
        public ?string $numeroSolicitud,
        public ?string $tituloEstudio,
        public ?string $investigadorId,
        public ?string $solicitanteNombre,
        public string $estado,
        public DateTimeImmutable $fecha,
    ) {}
}
