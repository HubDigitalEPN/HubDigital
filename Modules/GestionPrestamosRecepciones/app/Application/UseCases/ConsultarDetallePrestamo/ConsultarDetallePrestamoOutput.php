<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDetallePrestamo;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoPrestamo;

/**
 * Output DTO con el detalle completo de un préstamo: datos del préstamo, su acta y
 * su solicitud asociada, listos para renderizar en las pantallas de detalle.
 *
 * Los campos de acta/solicitud son anulables porque un préstamo podría no tener
 * acta o solicitud resuelta.
 */
final readonly class ConsultarDetallePrestamoOutput
{
    public function __construct(
        // ── Préstamo ──────────────────────────────────────────────────────────
        public string $prestamoId,
        public string $investigadorId,
        public EstadoPrestamo $estadoPrestamo,
        public ?DateTimeImmutable $iniciadoEn,
        public ?DateTimeImmutable $fechaFin,
        // ── Acta ──────────────────────────────────────────────────────────────
        public ?string $actaId,
        public string $numeroPrestamo,
        public ?string $actaEstado,
        public ?string $tipoPrestamo,
        public ?string $alcancePrestamo,
        public ?DateTimeImmutable $fechaInicioActa,
        public ?DateTimeImmutable $fechaFinActa,
        public ?string $condicionesGenerales,
        // ── Solicitud ─────────────────────────────────────────────────────────
        public ?string $solicitudId,
        public ?string $solicitudEstado,
        public ?string $numeroSolicitud,
        public ?string $tituloEstudio,
        public ?string $institucionAdscripcion,
        public ?string $lineaInvestigacion,
        public ?string $propositoPrestamo,
        public ?int $duracionPropuestaMeses,
    ) {}
}
