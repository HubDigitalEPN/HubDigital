<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Entities;

use DateTimeImmutable;
use InvalidArgumentException;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitudProrroga;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudProrrogaId;

/**
 * Registro de una solicitud de prórroga realizada por el investigador sobre un
 * préstamo activo. Conserva la fecha propuesta y la justificación, y el comentario
 * del curador al resolverla.
 *
 * La máquina de estados del préstamo vive en {@see Prestamo}; esta entidad es el
 * historial/petición y avanza solicitada → aprobada | rechazada.
 *
 * Construir vía {@see self::solicitar()}; rehidratar desde persistencia vía
 * {@see self::reconstituir()}.
 */
final class SolicitudProrroga
{
    private function __construct(
        private readonly SolicitudProrrogaId $id,
        private readonly PrestamoId $prestamoId,
        private readonly DateTimeImmutable $fechaPropuesta,
        private readonly string $justificacion,
        private EstadoSolicitudProrroga $estado,
        private readonly DateTimeImmutable $solicitadaEn,
        private ?string $comentarioResolucion,
        private ?DateTimeImmutable $resueltaEn,
    ) {}

    /**
     * El investigador registra una nueva solicitud de prórroga.
     *
     * @throws InvalidArgumentException Si no se aporta una justificación.
     */
    public static function solicitar(
        SolicitudProrrogaId $id,
        PrestamoId $prestamoId,
        DateTimeImmutable $fechaPropuesta,
        string $justificacion,
        DateTimeImmutable $solicitadaEn,
    ): self {
        if (trim($justificacion) === '') {
            throw new InvalidArgumentException('La solicitud de prórroga requiere una justificación.');
        }

        return new self(
            id: $id,
            prestamoId: $prestamoId,
            fechaPropuesta: $fechaPropuesta,
            justificacion: $justificacion,
            estado: EstadoSolicitudProrroga::Solicitada,
            solicitadaEn: $solicitadaEn,
            comentarioResolucion: null,
            resueltaEn: null,
        );
    }

    /**
     * Reconstitución desde persistencia.
     */
    public static function reconstituir(
        SolicitudProrrogaId $id,
        PrestamoId $prestamoId,
        DateTimeImmutable $fechaPropuesta,
        string $justificacion,
        EstadoSolicitudProrroga $estado,
        DateTimeImmutable $solicitadaEn,
        ?string $comentarioResolucion,
        ?DateTimeImmutable $resueltaEn,
    ): self {
        return new self(
            id: $id,
            prestamoId: $prestamoId,
            fechaPropuesta: $fechaPropuesta,
            justificacion: $justificacion,
            estado: $estado,
            solicitadaEn: $solicitadaEn,
            comentarioResolucion: $comentarioResolucion,
            resueltaEn: $resueltaEn,
        );
    }

    /**
     * El curador aprueba la solicitud. Solo permitido desde Solicitada.
     */
    public function aprobar(DateTimeImmutable $resueltaEn): void
    {
        $this->asegurarPendiente('aprobar');

        $this->estado = EstadoSolicitudProrroga::Aprobada;
        $this->resueltaEn = $resueltaEn;
    }

    /**
     * El curador rechaza la solicitud aportando un comentario obligatorio.
     * Solo permitido desde Solicitada.
     *
     * @throws InvalidArgumentException Si el comentario está vacío.
     */
    public function rechazar(string $comentario, DateTimeImmutable $resueltaEn): void
    {
        $this->asegurarPendiente('rechazar');

        if (trim($comentario) === '') {
            throw new InvalidArgumentException('El rechazo de una prórroga requiere un comentario.');
        }

        $this->estado = EstadoSolicitudProrroga::Rechazada;
        $this->comentarioResolucion = $comentario;
        $this->resueltaEn = $resueltaEn;
    }

    private function asegurarPendiente(string $accion): void
    {
        if (! $this->estado->equals(EstadoSolicitudProrroga::Solicitada)) {
            throw new InvalidArgumentException(
                "No se puede {$accion} una solicitud de prórroga que ya fue resuelta."
            );
        }
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    public function id(): SolicitudProrrogaId
    {
        return $this->id;
    }

    public function prestamoId(): PrestamoId
    {
        return $this->prestamoId;
    }

    public function fechaPropuesta(): DateTimeImmutable
    {
        return $this->fechaPropuesta;
    }

    public function justificacion(): string
    {
        return $this->justificacion;
    }

    public function estado(): EstadoSolicitudProrroga
    {
        return $this->estado;
    }

    public function solicitadaEn(): DateTimeImmutable
    {
        return $this->solicitadaEn;
    }

    public function comentarioResolucion(): ?string
    {
        return $this->comentarioResolucion;
    }

    public function resueltaEn(): ?DateTimeImmutable
    {
        return $this->resueltaEn;
    }
}
