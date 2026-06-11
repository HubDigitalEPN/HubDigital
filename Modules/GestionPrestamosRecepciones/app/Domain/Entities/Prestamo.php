<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Entities;

use DateTimeImmutable;
use InvalidArgumentException;
use Modules\GestionPrestamosRecepciones\Domain\Events\PrestamoActivado;
use Modules\GestionPrestamosRecepciones\Domain\Events\PrestamoHabilitadoParaEnvio;
use Modules\GestionPrestamosRecepciones\Domain\Events\PrestamoIniciado;
use Modules\GestionPrestamosRecepciones\Domain\Events\ProrrogaAprobada;
use Modules\GestionPrestamosRecepciones\Domain\Events\RecordatorioDevolucionEnviado;
use Modules\GestionPrestamosRecepciones\Domain\Events\VerificacionEntregaAprobada;
use Modules\GestionPrestamosRecepciones\Domain\Events\VerificacionEntregaRegistrada;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\TransicionDeEstadoInvalidaException;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ActaPrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\AlcancePrestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoRecordatorio;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;

/**
 * Agregado raíz que representa un préstamo activo de especímenes, una vez que el
 * acta ha sido validada.
 *
 * Modela el ciclo de vida posterior a la emisión del acta mediante una máquina de
 * estados (ver {@see EstadoPrestamo}): el flujo nacional arranca En tránsito,
 * mientras que el internacional arranca pendiente del documento del Ministerio.
 * Tras la verificación de entrega pasa a Activo, y vence al superarse la fecha de
 * fin. También calcula y registra los recordatorios de devolución. Cada transición
 * de negocio emite eventos de dominio.
 *
 * Construir vía {@see self::iniciar()}; rehidratar desde persistencia vía
 * {@see self::reconstituir()} (sin emitir eventos).
 */
final class Prestamo
{
    /** @var list<object> Eventos de dominio acumulados, drenados con {@see pullEvents()}. */
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

    /**
     * Inicia un préstamo a partir de un acta validada.
     *
     * El estado inicial depende del alcance: los préstamos internacionales arrancan
     * en PendienteDocumentoMinisterio (a la espera del documento del MAE) y los
     * nacionales directamente En tránsito.
     *
     * @throws InvalidArgumentException Si la fecha de fin no es posterior a la de inicio.
     */
    public static function iniciar(
        PrestamoId $id,
        ActaPrestamoId $actaPrestamoId,
        string $investigadorId,
        AlcancePrestamo $alcancePrestamo,
        DateTimeImmutable $iniciadoEn,
        DateTimeImmutable $fechaFin,
    ): self {
        if ($fechaFin <= $iniciadoEn) {
            throw new InvalidArgumentException('La fecha de fin debe ser posterior a la fecha de inicio del préstamo.');
        }

        $estadoInicial = $alcancePrestamo->esInternacional()
            ? EstadoPrestamo::PendienteDocumentoMinisterio
            : EstadoPrestamo::EnTransito;

        $prestamo = new self(
            id: $id,
            actaPrestamoId: $actaPrestamoId,
            investigadorId: $investigadorId,
            estado: $estadoInicial,
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

    /**
     * El curador habilita el envío tras recibir el documento del Ministerio.
     * Solo permitido cuando el préstamo está en PendienteDocumentoMinisterio (flujo internacional).
     */
    public function habilitarEnvio(string $curadorId): void
    {
        if (! $this->estado->equals(EstadoPrestamo::PendienteDocumentoMinisterio)) {
            throw TransicionDeEstadoInvalidaException::para(
                'Prestamo',
                $this->estado->name,
                'habilitarEnvio — el préstamo debe estar pendiente de documento del ministerio'
            );
        }

        $this->estado = EstadoPrestamo::EnTransito;

        $this->events[] = new PrestamoHabilitadoParaEnvio(
            prestamoId: $this->id,
            curadorId: $curadorId,
            ocurridoEn: new DateTimeImmutable,
        );
    }

    /**
     * El investigador registra la verificación de entrega de los especímenes recibidos.
     * Transiciona de EnTransito a PendienteAprobacionVerificacion.
     */
    public function registrarVerificacion(DateTimeImmutable $ahora): void
    {
        if (! $this->estado->equals(EstadoPrestamo::EnTransito)) {
            throw TransicionDeEstadoInvalidaException::para(
                'Prestamo',
                $this->estado->name,
                'registrarVerificacion — el préstamo debe estar en tránsito'
            );
        }

        $this->estado = EstadoPrestamo::PendienteAprobacionVerificacion;

        $this->events[] = new VerificacionEntregaRegistrada(
            prestamoId: $this->id,
            investigadorId: $this->investigadorId,
            ocurridoEn: $ahora,
        );
    }

    /**
     * El curador aprueba la verificación de entrega y activa el préstamo.
     * Emite tanto VerificacionEntregaAprobada como PrestamoActivado.
     * Solo permitido desde PendienteAprobacionVerificacion.
     */
    public function aprobarVerificacion(string $curadorId, DateTimeImmutable $ahora): void
    {
        if (! $this->estado->equals(EstadoPrestamo::PendienteAprobacionVerificacion)) {
            throw TransicionDeEstadoInvalidaException::para(
                'Prestamo',
                $this->estado->name,
                'aprobarVerificacion — el préstamo debe estar pendiente de aprobación de verificación'
            );
        }

        $this->estado = EstadoPrestamo::Activo;

        $this->events[] = new VerificacionEntregaAprobada(
            prestamoId: $this->id,
            curadorId: $curadorId,
            ocurridoEn: $ahora,
        );

        $this->events[] = new PrestamoActivado(
            prestamoId: $this->id,
            curadorId: $curadorId,
            ocurridoEn: $ahora,
        );
    }

    /**
     * El curador prorroga el préstamo extendiendo su fecha de fin.
     *
     * @throws TransicionDeEstadoInvalidaException Si la nueva fecha de fin no es posterior a la actual.
     */
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
            prestamoId: $this->id,
            investigadorId: $this->investigadorId,
            estadoRecordatorio: $estado,
            ocurridoEn: $ahora,
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
