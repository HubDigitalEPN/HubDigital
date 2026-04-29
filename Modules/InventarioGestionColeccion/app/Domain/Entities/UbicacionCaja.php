<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\Entities;

use Modules\InventarioGestionColeccion\Domain\Exceptions\UbicacionYaCerradaException;
use Modules\InventarioGestionColeccion\Domain\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\ValueObjects\RanuraId;
use Modules\InventarioGestionColeccion\Domain\ValueObjects\UbicacionCajaId;

class UbicacionCaja
{
    private function __construct(
        private readonly UbicacionCajaId $id,
        private readonly CajaId $cajaId,
        private readonly RanuraId $ranuraGabineteId,
        private readonly \DateTimeImmutable $ingresadaEn,
        private ?\DateTimeImmutable $retiradaEn,
    ) {}

    public static function registrar(
        UbicacionCajaId $id,
        CajaId $cajaId,
        RanuraId $ranuraGabineteId,
        \DateTimeImmutable $ingresadaEn,
    ): self {
        return new self(
            id: $id,
            cajaId: $cajaId,
            ranuraGabineteId: $ranuraGabineteId,
            ingresadaEn: $ingresadaEn,
            retiradaEn: null,
        );
    }

    public static function reconstituir(
        UbicacionCajaId $id,
        CajaId $cajaId,
        RanuraId $ranuraGabineteId,
        \DateTimeImmutable $ingresadaEn,
        ?\DateTimeImmutable $retiradaEn,
    ): self {
        return new self(
            id: $id,
            cajaId: $cajaId,
            ranuraGabineteId: $ranuraGabineteId,
            ingresadaEn: $ingresadaEn,
            retiradaEn: $retiradaEn,
        );
    }

    public function cerrar(\DateTimeImmutable $retiradaEn): void
    {
        if ($this->retiradaEn !== null) {
            throw new UbicacionYaCerradaException($this->id);
        }

        $this->retiradaEn = $retiradaEn;
    }

    public function estaActiva(): bool
    {
        return $this->retiradaEn === null;
    }

    public function id(): UbicacionCajaId
    {
        return $this->id;
    }

    public function cajaId(): CajaId
    {
        return $this->cajaId;
    }

    public function ranuraGabineteId(): RanuraId
    {
        return $this->ranuraGabineteId;
    }

    public function ingresadaEn(): \DateTimeImmutable
    {
        return $this->ingresadaEn;
    }

    public function retiradaEn(): ?\DateTimeImmutable
    {
        return $this->retiradaEn;
    }
}
