<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EntidadDepositanteId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TipoEntidadDepositante;

class EntidadDepositante
{
    private function __construct(
        private readonly EntidadDepositanteId $id,
        private string $nombre,
        private TipoEntidadDepositante $tipo,
        private string $contacto,
    ) {}

    public static function crear(
        EntidadDepositanteId $id,
        string $nombre,
        string $tipo,
        string $contacto,
    ): self {
        $tipoVO = TipoEntidadDepositante::tryFrom($tipo);

        if ($tipoVO === null) {
            throw new \InvalidArgumentException(
                "Tipo de entidad depositante inválido: '{$tipo}'. Valores válidos: ".
                implode(', ', array_column(TipoEntidadDepositante::cases(), 'value'))
            );
        }

        return new self(
            id: $id,
            nombre: trim($nombre),
            tipo: $tipoVO,
            contacto: trim($contacto),
        );
    }

    public static function reconstituir(
        EntidadDepositanteId $id,
        string $nombre,
        TipoEntidadDepositante $tipo,
        string $contacto,
    ): self {
        return new self(id: $id, nombre: $nombre, tipo: $tipo, contacto: $contacto);
    }

    public function actualizar(string $nombre, string $tipo, string $contacto): void
    {
        $tipoVO = TipoEntidadDepositante::tryFrom($tipo);

        if ($tipoVO === null) {
            throw new \InvalidArgumentException("Tipo de entidad depositante inválido: '{$tipo}'");
        }

        $this->nombre = trim($nombre);
        $this->tipo = $tipoVO;
        $this->contacto = trim($contacto);
    }

    public function id(): EntidadDepositanteId
    {
        return $this->id;
    }

    public function nombre(): string
    {
        return $this->nombre;
    }

    public function tipo(): TipoEntidadDepositante
    {
        return $this->tipo;
    }

    public function contacto(): string
    {
        return $this->contacto;
    }
}
