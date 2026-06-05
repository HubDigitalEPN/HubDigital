<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Exceptions\RanuraYaOcupadaException;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\GabineteId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\RanuraId;

class RanuraGabinete
{
    private function __construct(
        private readonly RanuraId $id,
        private readonly GabineteId $gabineteId,
        private readonly int $numeroRanura,
        private ?CajaId $cajaActualId,
        private bool $activa,
    ) {}

    public static function crear(
        RanuraId $id,
        GabineteId $gabineteId,
        int $numeroRanura,
    ): self {
        if ($numeroRanura < 1) {
            throw new \InvalidArgumentException('El número de ranura debe ser mayor a 0.');
        }

        return new self(
            id: $id,
            gabineteId: $gabineteId,
            numeroRanura: $numeroRanura,
            cajaActualId: null,
            activa: true,
        );
    }

    public static function reconstituir(
        RanuraId $id,
        GabineteId $gabineteId,
        int $numeroRanura,
        ?CajaId $cajaActualId,
        bool $activa,
    ): self {
        return new self(
            id: $id,
            gabineteId: $gabineteId,
            numeroRanura: $numeroRanura,
            cajaActualId: $cajaActualId,
            activa: $activa,
        );
    }

    public function asignarCaja(CajaId $cajaId): void
    {
        if ($this->cajaActualId !== null) {
            throw new RanuraYaOcupadaException($this->id, $this->cajaActualId);
        }

        $this->cajaActualId = $cajaId;
    }

    public function liberarCaja(): void
    {
        $this->cajaActualId = null;
    }

    public function desactivar(): void
    {
        $this->activa = false;
    }

    public function activar(): void
    {
        $this->activa = true;
    }

    public function estaOcupada(): bool
    {
        return $this->cajaActualId !== null;
    }

    public function id(): RanuraId
    {
        return $this->id;
    }

    public function gabineteId(): GabineteId
    {
        return $this->gabineteId;
    }

    public function numeroRanura(): int
    {
        return $this->numeroRanura;
    }

    public function cajaActualId(): ?CajaId
    {
        return $this->cajaActualId;
    }

    public function activa(): bool
    {
        return $this->activa;
    }
}
