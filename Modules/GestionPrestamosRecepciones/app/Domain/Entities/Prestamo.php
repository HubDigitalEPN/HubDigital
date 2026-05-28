<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Entities;

use DateTimeImmutable;
use InvalidArgumentException;
use Modules\GestionPrestamosRecepciones\Domain\Events\PrestamoIniciado;
use Modules\GestionPrestamosRecepciones\Domain\Events\ProrrogaAprobada;
use Modules\GestionPrestamosRecepciones\Domain\Events\RecordatorioDevolucionEnviado;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\TransicionDeEstadoInvalidaException;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ActaPrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoRecordatorio;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;

final class Prestamo
{
    /** @var list<object> */
    private array $events = [];

    private function __construct(
        private readonly PrestamoId $id,
        private readonly ActaPrestamoId $actaPrestamoId,
        private readonly string $investigadorId,
        private EstadoPrestamo $estado,
        private readonly DateTimeImmutable $iniciadoEn,
        private DateTimeImmutable $fechaFin,
    ) {}

    // ── Named constructors ────────────────────────────────────────────────────

    public static function iniciar(
        PrestamoId $id,
        ActaPrestamoId $actaPrestamoId,
        string $investigadorId,
        DateTimeImmutable $iniciadoEn,
        DateTimeImmutable $fechaFin,
    ): self {
        if ($fechaFin <= $iniciadoEn) {
            throw new InvalidArgumentException('La fecha de fin debe ser posterior a la fecha de inicio del préstamo.');
        }

        $prestamo = new self(
            id: $id,
            actaPrestamoId: $actaPrestamoId,
            investigadorId: $investigadorId,
            estado: EstadoPrestamo::Activo,
            iniciadoEn: $iniciadoEn,
            fechaFin: $fechaFin,
        );

        $prestamo->events[] = new PrestamoIniciado(
            prestamoId: $id,
            actaPrestamoId: $actaPrestamoId,
            investigadorId: $investigadorId,
            ocurridoEn: $iniciadoEn,
        );

        return $prestamo;
    }

    /**
     * Reconstitución desde persistencia — no registra eventos de dominio.
     */
    public static function reconstituir(
        PrestamoId $id,
        ActaPrestamoId $actaPrestamoId,
        string $investigadorId,
        EstadoPrestamo $estado,
        DateTimeImmutable $iniciadoEn,
        DateTimeImmutable $fechaFin,
    ): self {
        return new self(
            id: $id,
            actaPrestamoId: $actaPrestamoId,
            investigadorId: $investigadorId,
            estado: $estado,
            iniciadoEn: $iniciadoEn,
            fechaFin: $fechaFin,
        );
    }

    // ── Business methods ──────────────────────────────────────────────────────

    public function prorrogar(string $curadorId, DateTimeImmutable $nuevaFechaFin): void
    {
        if ($nuevaFechaFin <= $this->fechaFin) {
            throw TransicionDeEstadoInvalidaException::para(
                'Prestamo',
                $this->estado->name,
                'prorrogar — la nueva fecha de fin debe ser posterior a la actual'
            );
        }

        $fechaAnterior = $this->fechaFin;
        $this->fechaFin = $nuevaFechaFin;

        $this->events[] = new ProrrogaAprobada(
            prestamoId: $this->id,
            curadorId: $curadorId,
            nuevaFechaFin: $nuevaFechaFin,
            fechaAnterior: $fechaAnterior,
            ocurridoEn: new DateTimeImmutable,
        );
    }

    /**
     * Determina si aplica enviar un recordatorio según la configuración de días antes.
     * Retorna Vencido solo 1 día después del vencimiento (para evitar notificaciones repetidas).
     * Consulta pura — no muta estado ni registra eventos.
     *
     * @param  list<int>  $diasAntes
     */
    public function evaluarEstadoRecordatorio(
        array $diasAntes,
        DateTimeImmutable $ahora,
    ): ?EstadoRecordatorio {
        if ($ahora >= $this->fechaFin->modify('+1 day')) {
            return EstadoRecordatorio::Vencido;
        }

        $diasRestantes = (int) ceil(
            ($this->fechaFin->getTimestamp() - $ahora->getTimestamp()) / 86400
        );

        foreach ($diasAntes as $umbral) {
            if ($diasRestantes <= $umbral) {
                return EstadoRecordatorio::ProximoAVencer;
            }
        }

        return null;
    }

    /**
     * Transiciona el préstamo a Vencido y registra el evento de recordatorio.
     * Solo actúa si el préstamo está en estado Activo.
     */
    public function vencer(DateTimeImmutable $ahora): void
    {
        if (! $this->estado->equals(EstadoPrestamo::Activo)) {
            return;
        }

        $this->estado = EstadoPrestamo::Vencido;

        $this->events[] = new RecordatorioDevolucionEnviado(
            prestamoId: $this->id,
            investigadorId: $this->investigadorId,
            estadoRecordatorio: EstadoRecordatorio::Vencido,
            ocurridoEn: $ahora,
        );
    }

    /**
     * Registra en el agregado que se envió un recordatorio de devolución.
     * Debe llamarse después de haber despachado la notificación vía Port.
     */
    public function registrarEnvioDeRecordatorio(
        EstadoRecordatorio $estado,
        DateTimeImmutable $ahora,
    ): void {
        $this->events[] = new RecordatorioDevolucionEnviado(
            prestamoId:          $this->id,
            investigadorId:      $this->investigadorId,
            estadoRecordatorio:  $estado,
            ocurridoEn:          $ahora,
        );
    }

    /**
     * Retorna y vacía los eventos de dominio registrados en esta instancia.
     *
     * @return list<object>
     */
    public function pullEvents(): array
    {
        $events = $this->events;
        $this->events = [];

        return $events;
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    public function id(): PrestamoId
    {
        return $this->id;
    }

    public function actaPrestamoId(): ActaPrestamoId
    {
        return $this->actaPrestamoId;
    }

    public function investigadorId(): string
    {
        return $this->investigadorId;
    }

    public function estado(): EstadoPrestamo
    {
        return $this->estado;
    }

    public function iniciadoEn(): DateTimeImmutable
    {
        return $this->iniciadoEn;
    }

    public function fechaFin(): DateTimeImmutable
    {
        return $this->fechaFin;
    }
}
