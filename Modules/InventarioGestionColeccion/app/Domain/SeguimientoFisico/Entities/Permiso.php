<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\PermisoId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TipoPermiso;

class Permiso
{
    private function __construct(
        private readonly PermisoId $id,
        private TipoPermiso $tipo,
        private ?string $numero,
        private ?string $responsable,
        private ?string $detalles,
    ) {}

    public static function crear(
        PermisoId $id,
        string $tipo,
        ?string $numero = null,
        ?string $responsable = null,
        ?string $detalles = null,
    ): self {
        return new self(
            id: $id,
            tipo: self::resolverTipo($tipo),
            numero: self::normalizarOpcional($numero),
            responsable: self::normalizarOpcional($responsable),
            detalles: self::normalizarOpcional($detalles),
        );
    }

    public static function reconstituir(
        PermisoId $id,
        TipoPermiso $tipo,
        ?string $numero,
        ?string $responsable,
        ?string $detalles,
    ): self {
        return new self(id: $id, tipo: $tipo, numero: $numero, responsable: $responsable, detalles: $detalles);
    }

    public function actualizar(
        string $tipo,
        ?string $numero = null,
        ?string $responsable = null,
        ?string $detalles = null,
    ): void {
        $this->tipo = self::resolverTipo($tipo);
        $this->numero = self::normalizarOpcional($numero);
        $this->responsable = self::normalizarOpcional($responsable);
        $this->detalles = self::normalizarOpcional($detalles);
    }

    private static function resolverTipo(string $tipo): TipoPermiso
    {
        $vo = TipoPermiso::desdeValorFlexible($tipo);

        if ($vo === null) {
            throw new \InvalidArgumentException(
                "Tipo de permiso inválido: '{$tipo}'. Valores válidos: ".
                implode(', ', TipoPermiso::valoresAceptados())
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

    public function id(): PermisoId
    {
        return $this->id;
    }

    public function tipo(): TipoPermiso
    {
        return $this->tipo;
    }

    public function numero(): ?string
    {
        return $this->numero;
    }

    public function responsable(): ?string
    {
        return $this->responsable;
    }

    public function detalles(): ?string
    {
        return $this->detalles;
    }
}
