<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarMisSolicitudes;

use DateTimeImmutable;

/**
 * Fila de lectura del listado "Mis solicitudes" del investigador.
 *
 * {@see ConsultarMisSolicitudesOutput}
 */
final readonly class FilaMiSolicitud
{
    public function __construct(
        public string $solicitudId,
        public ?string $numeroSolicitud,
        public ?string $tituloEstudio,
        public string $estado,
        public DateTimeImmutable $fecha,
        public ?string $actaId,
        public ?string $actaEstado,
    ) {}
}
