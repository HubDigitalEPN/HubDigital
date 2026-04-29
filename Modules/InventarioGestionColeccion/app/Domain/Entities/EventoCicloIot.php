<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\Entities;

use Modules\InventarioGestionColeccion\Domain\ValueObjects\ActorRol;

class EventoCicloIot
{
    private function __construct(
        private readonly string $tipoAgregado,
        private readonly string $agregadoId,
        private readonly string $tipoEvento,
        private readonly int $versionEvento,
        private readonly array $datos,
        private readonly ?string $actorId,
        private readonly ActorRol $actorRol,
        private readonly \DateTimeImmutable $ocurridoEn,
    ) {}

    public static function registrar(
        string $tipoAgregado,
        string $agregadoId,
        string $tipoEvento,
        int $versionEvento,
        array $datos,
        ?string $actorId,
        ActorRol $actorRol,
        \DateTimeImmutable $ocurridoEn,
    ): self {
        return new self(
            tipoAgregado: $tipoAgregado,
            agregadoId: $agregadoId,
            tipoEvento: $tipoEvento,
            versionEvento: $versionEvento,
            datos: $datos,
            actorId: $actorId,
            actorRol: $actorRol,
            ocurridoEn: $ocurridoEn,
        );
    }

    public function tipoAgregado(): string
    {
        return $this->tipoAgregado;
    }

    public function agregadoId(): string
    {
        return $this->agregadoId;
    }

    public function tipoEvento(): string
    {
        return $this->tipoEvento;
    }

    public function versionEvento(): int
    {
        return $this->versionEvento;
    }

    public function datos(): array
    {
        return $this->datos;
    }

    public function actorId(): ?string
    {
        return $this->actorId;
    }

    public function actorRol(): ActorRol
    {
        return $this->actorRol;
    }

    public function ocurridoEn(): \DateTimeImmutable
    {
        return $this->ocurridoEn;
    }
}
