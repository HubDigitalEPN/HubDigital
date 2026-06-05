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
        private ?TipoEntidadDepositante $tipo,
        private ?string $contacto,
    ) {}

    public static function crear(
        EntidadDepositanteId $id,
        string $nombre,
        ?string $tipo = null,
        ?string $contacto = null,
    ): self {
        return new self(
            id: $id,
            nombre: trim($nombre),
            tipo: self::resolverTipo($tipo),
            contacto: self::normalizarOpcional($contacto),
        );
    }

    public static function reconstituir(
        EntidadDepositanteId $id,
        string $nombre,
        ?TipoEntidadDepositante $tipo,
        ?string $contacto,
    ): self {
        return new self(id: $id, nombre: $nombre, tipo: $tipo, contacto: $contacto);
    }

    public function actualizar(string $nombre, ?string $tipo = null, ?string $contacto = null): void
    {
        $this->nombre = trim($nombre);
        $this->tipo = self::resolverTipo($tipo);
        $this->contacto = self::normalizarOpcional($contacto);
    }

    private static function resolverTipo(?string $tipo): ?TipoEntidadDepositante
    {
        if ($tipo === null) {
            return null;
        }
        $trim = trim($tipo);
        if ($trim === '') {
            return null;
        }
        $vo = TipoEntidadDepositante::tryFrom($trim);

        if ($vo === null) {
            throw new \InvalidArgumentException(
                "Tipo de entidad depositante inválido: '{$tipo}'. Valores válidos: ".
                implode(', ', array_column(TipoEntidadDepositante::cases(), 'value'))
            );
        }

        return $vo;
    }

    private static function normalizarOpcional(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }
        $trim = trim($valor);

        return $trim === '' ? null : $trim;
    }

    public function id(): EntidadDepositanteId
    {
        return $this->id;
    }

    public function nombre(): string
    {
        return $this->nombre;
    }

    public function tipo(): ?TipoEntidadDepositante
    {
        return $this->tipo;
    }

    public function contacto(): ?string
    {
        return $this->contacto;
    }
}
