<?php

declare(strict_types=1);

use Modules\GestionPrestamosRecepciones\Domain\Entities\Prestamo;
use Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudProrroga;
use Modules\GestionPrestamosRecepciones\Domain\Events\ProrrogaAprobada;
use Modules\GestionPrestamosRecepciones\Domain\Events\ProrrogaRechazada;
use Modules\GestionPrestamosRecepciones\Domain\Events\ProrrogaSolicitada;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\TransicionDeEstadoInvalidaException;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ActaPrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\CodigoPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitudProrroga;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudProrrogaId;

function prestamoEnEstado(EstadoPrestamo $estado): Prestamo
{
    $inicio = new DateTimeImmutable('2026-01-01');

    return Prestamo::reconstituir(
        id: PrestamoId::generate(),
        actaPrestamoId: ActaPrestamoId::generate(),
        codigoPrestamo: CodigoPrestamo::fromParts(2026, 1),
        investigadorId: 'inv-001',
        estado: $estado,
        iniciadoEn: $inicio,
        fechaFin: $inicio->modify('+3 months'),
    );
}

function nuevaSolicitudProrroga(string $justificacion = 'Justificación válida'): SolicitudProrroga
{
    return SolicitudProrroga::solicitar(
        id: SolicitudProrrogaId::generate(),
        prestamoId: PrestamoId::generate(),
        fechaPropuesta: new DateTimeImmutable('2026-06-01'),
        justificacion: $justificacion,
        solicitadaEn: new DateTimeImmutable('2026-03-01'),
    );
}

// ── SolicitudProrroga ─────────────────────────────────────────────────────────

test('solicitar requiere justificación', function (): void {
    nuevaSolicitudProrroga('   ');
})->throws(InvalidArgumentException::class);

test('solicitar arranca en estado solicitada', function (): void {
    expect(nuevaSolicitudProrroga()->estado())->toBe(EstadoSolicitudProrroga::Solicitada);
});

test('aprobar marca la solicitud como aprobada', function (): void {
    $solicitud = nuevaSolicitudProrroga();
    $solicitud->aprobar(new DateTimeImmutable('2026-03-02'));

    expect($solicitud->estado())->toBe(EstadoSolicitudProrroga::Aprobada);
});

test('rechazar requiere comentario', function (): void {
    nuevaSolicitudProrroga()->rechazar('', new DateTimeImmutable('2026-03-02'));
})->throws(InvalidArgumentException::class);

test('rechazar con comentario marca rechazada y conserva el comentario', function (): void {
    $solicitud = nuevaSolicitudProrroga();
    $solicitud->rechazar('Plazo excesivo', new DateTimeImmutable('2026-03-02'));

    expect($solicitud->estado())->toBe(EstadoSolicitudProrroga::Rechazada)
        ->and($solicitud->comentarioResolucion())->toBe('Plazo excesivo');
});

// ── Prestamo: máquina de estados de prórroga ───────────────────────────────────

test('solicitar prórroga sobre préstamo activo lo deja en prórroga solicitada', function (): void {
    $prestamo = prestamoEnEstado(EstadoPrestamo::Activo);
    $prestamo->solicitarProrroga(new DateTimeImmutable('2026-02-01'));

    expect($prestamo->estado())->toBe(EstadoPrestamo::ProrrogaSolicitada)
        ->and($prestamo->pullEvents())->toHaveCount(1)
        ->and($prestamo->pullEvents())->toBe([]);
});

test('solicitar prórroga emite el evento ProrrogaSolicitada', function (): void {
    $prestamo = prestamoEnEstado(EstadoPrestamo::Activo);
    $prestamo->solicitarProrroga(new DateTimeImmutable('2026-02-01'));

    expect($prestamo->pullEvents()[0])->toBeInstanceOf(ProrrogaSolicitada::class);
});

test('no se puede solicitar prórroga de un préstamo vencido', function (): void {
    prestamoEnEstado(EstadoPrestamo::Vencido)->solicitarProrroga(new DateTimeImmutable);
})->throws(TransicionDeEstadoInvalidaException::class);

test('aprobar prórroga desde prórroga solicitada extiende la fecha y reactiva', function (): void {
    $prestamo = prestamoEnEstado(EstadoPrestamo::ProrrogaSolicitada);
    $nuevaFecha = new DateTimeImmutable('2026-09-01');
    $prestamo->aprobarProrroga('cur-001', $nuevaFecha);

    expect($prestamo->estado())->toBe(EstadoPrestamo::Activo)
        ->and($prestamo->fechaFin()->format('Y-m-d'))->toBe('2026-09-01')
        ->and($prestamo->pullEvents()[0])->toBeInstanceOf(ProrrogaAprobada::class);
});

test('no se puede aprobar prórroga si el préstamo no la tiene solicitada', function (): void {
    prestamoEnEstado(EstadoPrestamo::Activo)->aprobarProrroga('cur-001', new DateTimeImmutable('2026-09-01'));
})->throws(TransicionDeEstadoInvalidaException::class);

test('rechazar prórroga reactiva el préstamo conservando la fecha original', function (): void {
    $prestamo = prestamoEnEstado(EstadoPrestamo::ProrrogaSolicitada);
    $fechaOriginal = $prestamo->fechaFin()->format('Y-m-d');
    $prestamo->rechazarProrroga('cur-001', 'No procede');

    expect($prestamo->estado())->toBe(EstadoPrestamo::Activo)
        ->and($prestamo->fechaFin()->format('Y-m-d'))->toBe($fechaOriginal)
        ->and($prestamo->pullEvents()[0])->toBeInstanceOf(ProrrogaRechazada::class);
});
