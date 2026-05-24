<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Entities;

use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoRegistroEspecimen;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\RegistroEspecimenId;

final class RegistroEspecimen
{
    private RegistroEspecimenId $id;

    private string $nombreCientifico;

    private ?string $nombreCorregido;

    private EstadoRegistroEspecimen $estado;

    private bool $noCatalogado;

    private ?string $motivoJustificacion;

    private function __construct() {}

    public static function crear(
        RegistroEspecimenId $id,
        string $nombreCientifico,
        bool $noCatalogado = false,
        EstadoRegistroEspecimen $estadoInicial = EstadoRegistroEspecimen::Pendiente,
    ): self {
        if (trim($nombreCientifico) === '') {
            throw new \DomainException('El nombre científico del espécimen no puede estar vacío');
        }

        $registro = new self;
        $registro->id = $id;
        $registro->nombreCientifico = $nombreCientifico;
        $registro->nombreCorregido = null;
        $registro->estado = $estadoInicial;
        $registro->noCatalogado = $noCatalogado;
        $registro->motivoJustificacion = null;

        return $registro;
    }

    public function aceptarCorreccion(string $especieCorregida): void
    {
        if (trim($especieCorregida) === '') {
            throw new \DomainException('La especie corregida no puede estar vacía');
        }

        if (! $this->estado->equals(EstadoRegistroEspecimen::Pendiente)) {
            throw new \DomainException(
                sprintf(
                    'Solo se puede aceptar una corrección en estado "Pendiente", estado actual: "%s"',
                    $this->estado->value
                )
            );
        }

        $this->nombreCorregido = $especieCorregida;
        $this->noCatalogado = false;
        $this->estado = EstadoRegistroEspecimen::ValidadoTecnicamente;
    }

    public function justificar(string $motivo): void
    {
        if (trim($motivo) === '') {
            throw new \DomainException('El motivo de justificación no puede estar vacío');
        }

        if (! $this->noCatalogado) {
            throw new \DomainException(
                'Solo se pueden justificar registros marcados como no catalogados'
            );
        }

        $this->motivoJustificacion = $motivo;
        $this->estado = EstadoRegistroEspecimen::ValidacionManualPorCuraduria;
    }

    // ── Queries ──────────────────────────────────────────────────

    public function id(): RegistroEspecimenId
    {
        return $this->id;
    }

    public function nombreCientifico(): string
    {
        return $this->nombreCientifico;
    }

    public function nombreCorregido(): ?string
    {
        return $this->nombreCorregido;
    }

    public function estado(): EstadoRegistroEspecimen
    {
        return $this->estado;
    }

    public function esNoCatalogado(): bool
    {
        return $this->noCatalogado;
    }

    public function motivoJustificacion(): ?string
    {
        return $this->motivoJustificacion;
    }

    // ── Reconstitución desde persistencia ────────────────────────

    public static function reconstituir(
        RegistroEspecimenId $id,
        string $nombreCientifico,
        ?string $nombreCorregido,
        EstadoRegistroEspecimen $estado,
        bool $noCatalogado,
        ?string $motivoJustificacion,
    ): self {
        $registro = new self;
        $registro->id = $id;
        $registro->nombreCientifico = $nombreCientifico;
        $registro->nombreCorregido = $nombreCorregido;
        $registro->estado = $estado;
        $registro->noCatalogado = $noCatalogado;
        $registro->motivoJustificacion = $motivoJustificacion;

        return $registro;
    }
}
