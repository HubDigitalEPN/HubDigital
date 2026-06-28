<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Entities;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\Events\SolicitudPrestamoAprobada;
use Modules\GestionPrestamosRecepciones\Domain\Events\SolicitudPrestamoEnviada;
use Modules\GestionPrestamosRecepciones\Domain\Events\SolicitudPrestamoObservada;
use Modules\GestionPrestamosRecepciones\Domain\Events\SolicitudPrestamoRechazada;
use Modules\GestionPrestamosRecepciones\Domain\Events\SolicitudPrestamoRegistrada;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\SolicitudPrestamoIncompletaException;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\TransicionDeEstadoInvalidaException;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\AlcancePrestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\CodigoPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitud;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudPrestamoId;

/**
 * Agregado raíz que representa la solicitud de préstamo de especímenes que
 * presenta el investigador y que el curador revisa.
 *
 * Gestiona el ciclo de vida de la solicitud mediante una máquina de estados
 * (Borrador → Enviada → Observada/Aprobada/Rechazada, ver {@see EstadoSolicitud})
 * y emite eventos de dominio en cada transición de negocio. Las invariantes de
 * transición se validan en los métodos de negocio, nunca en los accessors.
 *
 * Construir vía {@see self::crear()} o {@see self::crearIncompleta()}; rehidratar
 * desde persistencia vía {@see self::reconstituir()} (sin emitir eventos).
 */
final class SolicitudPrestamo
{
    /** @var list<object> Eventos de dominio acumulados, drenados con {@see pullEvents()}. */
    private array $events = [];

    private function __construct(
        private readonly SolicitudPrestamoId $id,
        private readonly CodigoPrestamo $codigoPrestamo,
        private readonly string $investigadorId,
        private readonly AlcancePrestamo $alcancePrestamo,
        private EstadoSolicitud $estado,
        private ?string $tituloEstudio,
        private ?string $institucionAdscripcion,
        private ?string $lineaInvestigacion,
        private ?string $propositoPrestamo,
        private ?int $duracionPropuestaMeses,
        private ?string $justificacionExtendida,
        private ?string $comentarioCurador,
        /** @var list<ItemPrestamo> */
        private array $items,
        private ?DateTimeImmutable $enviadaEn,
        private ?DateTimeImmutable $resueltaEn,
        private ?string $resueltaPor,
    ) {}

    // ── Named constructors ────────────────────────────────────────────────────

    /**
     * Crea una nueva solicitud en estado borrador con todos los campos requeridos.
     *
     * @param  list<ItemPrestamo>  $items
     */
    public static function crear(
        SolicitudPrestamoId $id,
        CodigoPrestamo $codigoPrestamo,
        string $investigadorId,
        AlcancePrestamo $alcancePrestamo,
        string $tituloEstudio,
        string $institucionAdscripcion,
        string $lineaInvestigacion,
        string $propositoPrestamo,
        int $duracionPropuestaMeses,
        array $items,
        ?string $justificacionExtendida = null,
    ): self {
        $ahora = new DateTimeImmutable;

        $solicitud = new self(
            id: $id,
            codigoPrestamo: $codigoPrestamo,
            investigadorId: $investigadorId,
            alcancePrestamo: $alcancePrestamo,
            estado: EstadoSolicitud::Borrador,
            tituloEstudio: $tituloEstudio,
            institucionAdscripcion: $institucionAdscripcion,
            lineaInvestigacion: $lineaInvestigacion,
            propositoPrestamo: $propositoPrestamo,
            duracionPropuestaMeses: $duracionPropuestaMeses,
            justificacionExtendida: $justificacionExtendida,
            comentarioCurador: null,
            items: $items,
            enviadaEn: null,
            resueltaEn: null,
            resueltaPor: null,
        );

        $solicitud->events[] = new SolicitudPrestamoRegistrada(
            solicitudId: $id,
            investigadorId: $investigadorId,
            ocurridoEn: $ahora,
        );

        return $solicitud;
    }

    /**
     * Crea una nueva solicitud en estado borrador con información mínima.
     * Usada cuando el investigador guarda un borrador sin haber completado todos los campos.
     */
    public static function crearIncompleta(
        SolicitudPrestamoId $id,
        CodigoPrestamo $codigoPrestamo,
        string $investigadorId,
        AlcancePrestamo $alcancePrestamo,
    ): self {
        $ahora = new DateTimeImmutable;

        $solicitud = new self(
            id: $id,
            codigoPrestamo: $codigoPrestamo,
            investigadorId: $investigadorId,
            alcancePrestamo: $alcancePrestamo,
            estado: EstadoSolicitud::Borrador,
            tituloEstudio: null,
            institucionAdscripcion: null,
            lineaInvestigacion: null,
            propositoPrestamo: null,
            duracionPropuestaMeses: null,
            justificacionExtendida: null,
            comentarioCurador: null,
            items: [],
            enviadaEn: null,
            resueltaEn: null,
            resueltaPor: null,
        );

        $solicitud->events[] = new SolicitudPrestamoRegistrada(
            solicitudId: $id,
            investigadorId: $investigadorId,
            ocurridoEn: $ahora,
        );

        return $solicitud;
    }

    /**
     * Reconstitución desde persistencia — no registra eventos de dominio.
     *
     * @param  list<ItemPrestamo>  $items
     */
    public static function reconstituir(
        SolicitudPrestamoId $id,
        CodigoPrestamo $codigoPrestamo,
        string $investigadorId,
        AlcancePrestamo $alcancePrestamo,
        EstadoSolicitud $estado,
        ?string $tituloEstudio,
        ?string $institucionAdscripcion,
        ?string $lineaInvestigacion,
        ?string $propositoPrestamo,
        ?int $duracionPropuestaMeses,
        ?string $justificacionExtendida,
        ?string $comentarioCurador,
        array $items,
        ?DateTimeImmutable $enviadaEn,
        ?DateTimeImmutable $resueltaEn,
        ?string $resueltaPor,
    ): self {
        return new self(
            id: $id,
            codigoPrestamo: $codigoPrestamo,
            investigadorId: $investigadorId,
            alcancePrestamo: $alcancePrestamo,
            estado: $estado,
            tituloEstudio: $tituloEstudio,
            institucionAdscripcion: $institucionAdscripcion,
            lineaInvestigacion: $lineaInvestigacion,
            propositoPrestamo: $propositoPrestamo,
            duracionPropuestaMeses: $duracionPropuestaMeses,
            justificacionExtendida: $justificacionExtendida,
            comentarioCurador: $comentarioCurador,
            items: $items,
            enviadaEn: $enviadaEn,
            resueltaEn: $resueltaEn,
            resueltaPor: $resueltaPor,
        );
    }

    // ── Métodos de negocio ────────────────────────────────────────────────────

    /**
     * Actualiza los campos editables de la solicitud.
     * Permitido en estado Borrador u Observada (corrección tras devolución del curador).
     *
     * @param  list<ItemPrestamo>  $items
     */
    public function actualizar(
        string $tituloEstudio,
        string $institucionAdscripcion,
        string $lineaInvestigacion,
        string $propositoPrestamo,
        int $duracionPropuestaMeses,
        array $items,
        ?string $justificacionExtendida = null,
    ): void {
        $puedeEditar = $this->estado->equals(EstadoSolicitud::Borrador)
            || $this->estado->equals(EstadoSolicitud::Observada);

        if (! $puedeEditar) {
            throw TransicionDeEstadoInvalidaException::para(
                'SolicitudPrestamo',
                $this->estado->value,
                'actualizar',
            );
        }

        $this->tituloEstudio = $tituloEstudio;
        $this->institucionAdscripcion = $institucionAdscripcion;
        $this->lineaInvestigacion = $lineaInvestigacion;
        $this->propositoPrestamo = $propositoPrestamo;
        $this->duracionPropuestaMeses = $duracionPropuestaMeses;
        $this->justificacionExtendida = $justificacionExtendida;
        $this->items = $items;
    }

    /**
     * Transiciona la solicitud al estado Enviada.
     * Solo permitido desde Borrador u Observada, y con información completa.
     */
    public function enviar(): void
    {
        if (! $this->estado->puedeEnviar()) {
            throw TransicionDeEstadoInvalidaException::para(
                'SolicitudPrestamo',
                $this->estado->value,
                'enviar',
            );
        }

        if (! $this->estaCompleta()) {
            throw SolicitudPrestamoIncompletaException::paraEnvio();
        }

        $ahora = new DateTimeImmutable;

        $this->estado = EstadoSolicitud::Enviada;
        $this->enviadaEn = $ahora;

        $this->events[] = new SolicitudPrestamoEnviada(
            solicitudId: $this->id,
            enviadaEn: $ahora,
        );
    }

    /**
     * El curador devuelve la solicitud con una observación.
     * Solo permitido desde estado Enviada.
     */
    public function observar(string $curadorId, string $observacion): void
    {
        if (! $this->estado->puedeResolver()) {
            throw TransicionDeEstadoInvalidaException::para(
                'SolicitudPrestamo',
                $this->estado->value,
                'observar',
            );
        }

        $ahora = new DateTimeImmutable;

        $this->estado = EstadoSolicitud::Observada;
        $this->comentarioCurador = $observacion;

        $this->events[] = new SolicitudPrestamoObservada(
            solicitudId: $this->id,
            curadorId: $curadorId,
            observacion: $observacion,
            ocurridoEn: $ahora,
        );
    }

    /**
     * El curador aprueba la solicitud.
     * Solo permitido desde estado Enviada.
     */
    public function aprobar(string $curadorId): void
    {
        if (! $this->estado->puedeResolver()) {
            throw TransicionDeEstadoInvalidaException::para(
                'SolicitudPrestamo',
                $this->estado->value,
                'aprobar',
            );
        }

        $ahora = new DateTimeImmutable;

        $this->estado = EstadoSolicitud::Aprobada;
        $this->resueltaEn = $ahora;
        $this->resueltaPor = $curadorId;

        $this->events[] = new SolicitudPrestamoAprobada(
            solicitudId: $this->id,
            curadorId: $curadorId,
            ocurridoEn: $ahora,
        );
    }

    /**
     * El curador rechaza la solicitud.
     * Solo permitido desde estado Enviada.
     */
    public function rechazar(string $curadorId, string $motivo): void
    {
        if (! $this->estado->puedeResolver()) {
            throw TransicionDeEstadoInvalidaException::para(
                'SolicitudPrestamo',
                $this->estado->value,
                'rechazar',
            );
        }

        $ahora = new DateTimeImmutable;

        $this->estado = EstadoSolicitud::Rechazada;
        $this->resueltaEn = $ahora;
        $this->resueltaPor = $curadorId;

        $this->events[] = new SolicitudPrestamoRechazada(
            solicitudId: $this->id,
            curadorId: $curadorId,
            motivo: $motivo,
            ocurridoEn: $ahora,
        );
    }

    /**
     * Registra que el acta ha sido emitida para esta solicitud.
     * Solo permitido desde estado Aprobada.
     */
    public function emitirActa(string $pdfRuta): void
    {
        if (! $this->estado->equals(EstadoSolicitud::Aprobada)) {
            throw TransicionDeEstadoInvalidaException::para(
                'SolicitudPrestamo',
                $this->estado->value,
                'emitirActa',
            );
        }
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

    public function id(): SolicitudPrestamoId
    {
        return $this->id;
    }

    public function codigoPrestamo(): CodigoPrestamo
    {
        return $this->codigoPrestamo;
    }

    public function investigadorId(): string
    {
        return $this->investigadorId;
    }

    public function alcancePrestamo(): AlcancePrestamo
    {
        return $this->alcancePrestamo;
    }

    public function estado(): EstadoSolicitud
    {
        return $this->estado;
    }

    public function tituloEstudio(): ?string
    {
        return $this->tituloEstudio;
    }

    public function institucionAdscripcion(): ?string
    {
        return $this->institucionAdscripcion;
    }

    public function lineaInvestigacion(): ?string
    {
        return $this->lineaInvestigacion;
    }

    public function propositoPrestamo(): ?string
    {
        return $this->propositoPrestamo;
    }

    public function duracionPropuestaMeses(): ?int
    {
        return $this->duracionPropuestaMeses;
    }

    public function justificacionExtendida(): ?string
    {
        return $this->justificacionExtendida;
    }

    public function comentarioCurador(): ?string
    {
        return $this->comentarioCurador;
    }

    /** @return list<ItemPrestamo> */
    public function items(): array
    {
        return $this->items;
    }

    public function enviadaEn(): ?DateTimeImmutable
    {
        return $this->enviadaEn;
    }

    public function resueltaEn(): ?DateTimeImmutable
    {
        return $this->resueltaEn;
    }

    public function resueltaPor(): ?string
    {
        return $this->resueltaPor;
    }

    // ── Invariante privada ────────────────────────────────────────────────────

    private function estaCompleta(): bool
    {
        return $this->tituloEstudio !== null
            && $this->institucionAdscripcion !== null
            && $this->lineaInvestigacion !== null
            && $this->propositoPrestamo !== null
            && $this->duracionPropuestaMeses !== null
            && $this->duracionPropuestaMeses > 0
            && count($this->items) > 0;
    }
}
