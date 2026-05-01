<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\AlertaUbicacionId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoAlerta;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TipoAlerta;

class AlertaUbicacion
{
    private function __construct(
        private readonly AlertaUbicacionId $id,
        private readonly CajaId $cajaId,
        private readonly TipoAlerta $tipo,
        private EstadoAlerta $estado,
        private readonly array $datosContexto,
    ) {}

    public static function generar(
        AlertaUbicacionId $id,
        CajaId $cajaId,
        TipoAlerta $tipo,
        array $datosContexto = [],
    ): self {
        return new self(
            id: $id,
            cajaId: $cajaId,
            tipo: $tipo,
            estado: EstadoAlerta::Activa,
            datosContexto: $datosContexto,
        );
    }

    public static function reconstituir(
        AlertaUbicacionId $id,
        CajaId $cajaId,
        TipoAlerta $tipo,
        EstadoAlerta $estado,
        array $datosContexto,
    ): self {
        return new self(
            id: $id,
            cajaId: $cajaId,
            tipo: $tipo,
            estado: $estado,
            datosContexto: $datosContexto,
        );
    }

    public function resolver(string $motivoResolucion): void
    {
        if (!$this->estado->equals(EstadoAlerta::Activa)) {
            throw new \DomainException("Solo se puede resolver una alerta activa.");
        }

        $this->estado = EstadoAlerta::Resuelta;
    }

    public function ignorar(): void
    {
        if (!$this->estado->equals(EstadoAlerta::Activa)) {
            throw new \DomainException("Solo se puede ignorar una alerta activa.");
        }

        $this->estado = EstadoAlerta::Ignorada;
    }

    public function id(): AlertaUbicacionId
    {
        return $this->id;
    }

    public function cajaId(): CajaId
    {
        return $this->cajaId;
    }

    public function tipo(): TipoAlerta
    {
        return $this->tipo;
    }

    public function estado(): EstadoAlerta
    {
        return $this->estado;
    }

    public function datosContexto(): array
    {
        return $this->datosContexto;
    }
}
