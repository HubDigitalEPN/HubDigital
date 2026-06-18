<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Entities;

use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\AlertaSolicitudId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoRevisionAlerta;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoAlerta;

/**
 * Entidad local del agregado {@see SolicitudDeposito} que representa una alerta
 * (discrepancia de identidad, especie no listada, hallazgo taxonómico) que llega
 * con la solicitud justificada por el investigador.
 *
 * Tiene identidad propia (System ID) y ciclo de vida individual: nace
 * {@see EstadoRevisionAlerta::PendienteRevision} y el curador la transiciona a
 * aceptada o rechazada. Construir vía {@see registrar()}; rehidratar vía
 * {@see reconstituir()}.
 */
final class AlertaSolicitud
{
    private AlertaSolicitudId $id;

    private TipoAlerta $tipo;

    private ?string $justificacionInvestigador;

    private EstadoRevisionAlerta $estadoRevision;

    private function __construct() {}

    /**
     * Registra una alerta que llega justificada por el investigador, pendiente de
     * revisión por el curador.
     *
     * @throws \DomainException Si la justificación está vacía.
     */
    public static function registrar(
        AlertaSolicitudId $id,
        TipoAlerta $tipo,
        string $justificacionInvestigador,
    ): self {
        if (trim($justificacionInvestigador) === '') {
            throw new \DomainException('La justificación del investigador no puede estar vacía');
        }

        $alerta = new self;
        $alerta->id = $id;
        $alerta->tipo = $tipo;
        $alerta->justificacionInvestigador = $justificacionInvestigador;
        $alerta->estadoRevision = EstadoRevisionAlerta::PendienteRevision;

        return $alerta;
    }

    /**
     * El curador acepta la justificación de la alerta. Solo permitido mientras
     * está pendiente de revisión.
     *
     * @throws \DomainException Si la alerta ya fue revisada.
     */
    public function aceptar(): void
    {
        $this->garantizarPendiente('aceptar');

        $this->estadoRevision = EstadoRevisionAlerta::Aceptada;
    }

    /**
     * El curador rechaza la justificación de la alerta. Solo permitido mientras
     * está pendiente de revisión.
     *
     * @throws \DomainException Si la alerta ya fue revisada.
     */
    public function rechazar(): void
    {
        $this->garantizarPendiente('rechazar');

        $this->estadoRevision = EstadoRevisionAlerta::Rechazada;
    }

    // ── Queries ──────────────────────────────────────────────────

    public function id(): AlertaSolicitudId
    {
        return $this->id;
    }

    public function tipo(): TipoAlerta
    {
        return $this->tipo;
    }

    public function justificacionInvestigador(): ?string
    {
        return $this->justificacionInvestigador;
    }

    public function estadoRevision(): EstadoRevisionAlerta
    {
        return $this->estadoRevision;
    }

    public function estaPendiente(): bool
    {
        return $this->estadoRevision->equals(EstadoRevisionAlerta::PendienteRevision);
    }

    public function fueRechazada(): bool
    {
        return $this->estadoRevision->equals(EstadoRevisionAlerta::Rechazada);
    }

    // ── Reconstitución desde persistencia ────────────────────────

    public static function reconstituir(
        AlertaSolicitudId $id,
        TipoAlerta $tipo,
        ?string $justificacionInvestigador,
        EstadoRevisionAlerta $estadoRevision,
    ): self {
        $alerta = new self;
        $alerta->id = $id;
        $alerta->tipo = $tipo;
        $alerta->justificacionInvestigador = $justificacionInvestigador;
        $alerta->estadoRevision = $estadoRevision;

        return $alerta;
    }

    // ── Helpers privados ─────────────────────────────────────────

    private function garantizarPendiente(string $accion): void
    {
        if (! $this->estaPendiente()) {
            throw new \DomainException(
                sprintf(
                    'Solo se puede %s una alerta pendiente de revisión, estado actual: "%s"',
                    $accion,
                    $this->estadoRevision->value
                )
            );
        }
    }
}
