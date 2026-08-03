<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoReversionDetalle;

/**
 * Lo que le pasó a UN espécimen dentro de una edición masiva.
 *
 * Guarda el valor que tenía antes y el que se le escribió. Con esos dos datos
 * el deshacer puede, para cada fila por separado, comprobar si el campo sigue
 * como lo dejó la edición (y entonces revertirlo) o si alguien lo cambió
 * después (y entonces no tocarlo).
 */
final class DetalleEdicionMasiva
{
    private function __construct(
        private readonly string $id,
        private readonly string $edicionId,
        private readonly string $especimenId,
        private readonly ?string $valorPrevio,
        private readonly ?string $valorAplicado,
        private EstadoReversionDetalle $estadoReversion,
    ) {}

    public static function registrar(
        string $id,
        string $edicionId,
        string $especimenId,
        ?string $valorPrevio,
        ?string $valorAplicado,
    ): self {
        return new self($id, $edicionId, $especimenId, $valorPrevio, $valorAplicado, EstadoReversionDetalle::Pendiente);
    }

    public static function reconstituir(
        string $id,
        string $edicionId,
        string $especimenId,
        ?string $valorPrevio,
        ?string $valorAplicado,
        EstadoReversionDetalle $estadoReversion,
    ): self {
        return new self($id, $edicionId, $especimenId, $valorPrevio, $valorAplicado, $estadoReversion);
    }

    public function marcarComo(EstadoReversionDetalle $estado): void
    {
        $this->estadoReversion = $estado;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function edicionId(): string
    {
        return $this->edicionId;
    }

    public function especimenId(): string
    {
        return $this->especimenId;
    }

    public function valorPrevio(): ?string
    {
        return $this->valorPrevio;
    }

    public function valorAplicado(): ?string
    {
        return $this->valorAplicado;
    }

    public function estadoReversion(): EstadoReversionDetalle
    {
        return $this->estadoReversion;
    }
}
