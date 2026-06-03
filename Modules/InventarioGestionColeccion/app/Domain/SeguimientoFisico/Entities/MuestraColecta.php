<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoRevision;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\LocalidadId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\MuestraColectaId;

class MuestraColecta
{
    private function __construct(
        private readonly MuestraColectaId $id,
        private ?string $codigoMuestra,
        private ?\DateTimeImmutable $fechaColecta,
        private ?string $fechaVerbatim,
        private ?LocalidadId $localidadId,
        private ?string $localidadVerbatim,
        private ?string $colector,
        private ?string $samplingProtocol,
        private EstadoRevision $estadoRevision,
        private ?string $motivoRevision,
    ) {}

    public static function crear(
        MuestraColectaId $id,
        ?string $codigoMuestra = null,
        ?\DateTimeImmutable $fechaColecta = null,
        ?string $fechaVerbatim = null,
        ?string $localidadId = null,
        ?string $localidadVerbatim = null,
        ?string $colector = null,
        ?string $samplingProtocol = null,
        ?string $motivoRevision = null,
    ): self {
        return new self(
            id: $id,
            codigoMuestra: self::normalizarOpcional($codigoMuestra),
            fechaColecta: $fechaColecta,
            fechaVerbatim: self::normalizarOpcional($fechaVerbatim),
            localidadId: $localidadId !== null ? LocalidadId::desde($localidadId) : null,
            localidadVerbatim: self::normalizarOpcional($localidadVerbatim),
            colector: self::normalizarOpcional($colector),
            samplingProtocol: self::normalizarOpcional($samplingProtocol),
            estadoRevision: EstadoRevision::porDefecto(),
            motivoRevision: self::normalizarOpcional($motivoRevision),
        );
    }

    public static function reconstituir(
        MuestraColectaId $id,
        ?string $codigoMuestra,
        ?\DateTimeImmutable $fechaColecta,
        ?string $fechaVerbatim,
        ?LocalidadId $localidadId,
        ?string $localidadVerbatim,
        ?string $colector,
        ?string $samplingProtocol,
        EstadoRevision $estadoRevision,
        ?string $motivoRevision,
    ): self {
        return new self(
            id: $id,
            codigoMuestra: $codigoMuestra,
            fechaColecta: $fechaColecta,
            fechaVerbatim: $fechaVerbatim,
            localidadId: $localidadId,
            localidadVerbatim: $localidadVerbatim,
            colector: $colector,
            samplingProtocol: $samplingProtocol,
            estadoRevision: $estadoRevision,
            motivoRevision: $motivoRevision,
        );
    }

    public function confirmar(): void
    {
        if (! $this->estadoRevision->puedeConfirmarse()) {
            throw new \DomainException(
                "No se puede confirmar una muestra en estado '{$this->estadoRevision->value}'. ".
                "Sólo las muestras en estado 'pendiente' pueden confirmarse."
            );
        }
        $this->estadoRevision = EstadoRevision::Confirmada;
        $this->motivoRevision = null;
    }

    public function marcarParaRevision(string $motivo): void
    {
        $motivoNormalizado = trim($motivo);
        if ($motivoNormalizado === '') {
            throw new \InvalidArgumentException('El motivo de revisión es requerido.');
        }
        $this->estadoRevision = EstadoRevision::Pendiente;
        $this->motivoRevision = $motivoNormalizado;
    }

    public function enlazarLocalidad(LocalidadId $localidadId): void
    {
        $this->localidadId = $localidadId;
    }

    private static function normalizarOpcional(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }
        $trim = trim($valor);

        return $trim === '' ? null : $trim;
    }

    public function id(): MuestraColectaId
    {
        return $this->id;
    }

    public function codigoMuestra(): ?string
    {
        return $this->codigoMuestra;
    }

    public function fechaColecta(): ?\DateTimeImmutable
    {
        return $this->fechaColecta;
    }

    public function fechaVerbatim(): ?string
    {
        return $this->fechaVerbatim;
    }

    public function localidadId(): ?LocalidadId
    {
        return $this->localidadId;
    }

    public function localidadVerbatim(): ?string
    {
        return $this->localidadVerbatim;
    }

    public function colector(): ?string
    {
        return $this->colector;
    }

    public function samplingProtocol(): ?string
    {
        return $this->samplingProtocol;
    }

    public function estadoRevision(): EstadoRevision
    {
        return $this->estadoRevision;
    }

    public function motivoRevision(): ?string
    {
        return $this->motivoRevision;
    }
}
