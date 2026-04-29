<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\Entities;

use Modules\InventarioGestionColeccion\Domain\ValueObjects\CodigoGabinete;
use Modules\InventarioGestionColeccion\Domain\ValueObjects\GabineteId;

class Gabinete
{
    private function __construct(
        private readonly GabineteId $id,
        private readonly CodigoGabinete $codigo,
        private readonly string $nombre,
        private readonly int $totalRanuras,
        private bool $activo,
    ) {}

    public static function crear(
        GabineteId $id,
        CodigoGabinete $codigo,
        string $nombre,
        int $totalRanuras,
    ): self {
        if ($totalRanuras < 1) {
            throw new \InvalidArgumentException("Un gabinete debe tener al menos 1 ranura.");
        }

        return new self(
            id: $id,
            codigo: $codigo,
            nombre: $nombre,
            totalRanuras: $totalRanuras,
            activo: true,
        );
    }

    public static function reconstituir(
        GabineteId $id,
        CodigoGabinete $codigo,
        string $nombre,
        int $totalRanuras,
        bool $activo,
    ): self {
        return new self(
            id: $id,
            codigo: $codigo,
            nombre: $nombre,
            totalRanuras: $totalRanuras,
            activo: $activo,
        );
    }

    public function activar(): void
    {
        $this->activo = true;
    }

    public function desactivar(): void
    {
        $this->activo = false;
    }

    public function id(): GabineteId
    {
        return $this->id;
    }

    public function codigo(): CodigoGabinete
    {
        return $this->codigo;
    }

    public function nombre(): string
    {
        return $this->nombre;
    }

    public function totalRanuras(): int
    {
        return $this->totalRanuras;
    }

    public function activo(): bool
    {
        return $this->activo;
    }
}
