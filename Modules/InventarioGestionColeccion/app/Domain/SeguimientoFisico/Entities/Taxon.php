<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoTaxon;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\RangoTaxonomico;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TaxonId;

class Taxon
{
    private function __construct(
        private readonly TaxonId $id,
        private string $nombreCientifico,
        private readonly RangoTaxonomico $rango,
        private string $autor,
        private int $anioDescripcion,
        private EstadoTaxon $estado,
        private ?TaxonId $padreId,
    ) {}

    public static function crear(
        TaxonId $id,
        string $nombreCientifico,
        string $rango,
        string $autor,
        int $anioDescripcion,
        ?string $padreId = null,
    ): self {
        $rangoVO = RangoTaxonomico::tryFrom($rango);

        if ($rangoVO === null) {
            throw new \InvalidArgumentException(
                "Rango taxonómico inválido: '{$rango}'. Valores válidos: ".
                implode(', ', array_column(RangoTaxonomico::cases(), 'value'))
            );
        }

        return new self(
            id: $id,
            nombreCientifico: trim($nombreCientifico),
            rango: $rangoVO,
            autor: trim($autor),
            anioDescripcion: $anioDescripcion,
            estado: EstadoTaxon::Activo,
            padreId: $padreId !== null ? TaxonId::desde($padreId) : null,
        );
    }

    public static function reconstituir(
        TaxonId $id,
        string $nombreCientifico,
        RangoTaxonomico $rango,
        string $autor,
        int $anioDescripcion,
        EstadoTaxon $estado,
        ?TaxonId $padreId = null,
    ): self {
        return new self(
            id: $id,
            nombreCientifico: $nombreCientifico,
            rango: $rango,
            autor: $autor,
            anioDescripcion: $anioDescripcion,
            estado: $estado,
            padreId: $padreId,
        );
    }

    public function actualizar(string $nombreCientifico, string $autor, int $anioDescripcion): void
    {
        $this->nombreCientifico = trim($nombreCientifico);
        $this->autor = trim($autor);
        $this->anioDescripcion = $anioDescripcion;
    }

    public function desactivar(): void
    {
        $this->estado = EstadoTaxon::Inactivo;
    }

    public function id(): TaxonId
    {
        return $this->id;
    }

    public function nombreCientifico(): string
    {
        return $this->nombreCientifico;
    }

    public function rango(): RangoTaxonomico
    {
        return $this->rango;
    }

    public function autor(): string
    {
        return $this->autor;
    }

    public function anioDescripcion(): int
    {
        return $this->anioDescripcion;
    }

    public function estado(): EstadoTaxon
    {
        return $this->estado;
    }

    public function padreId(): ?TaxonId
    {
        return $this->padreId;
    }
}
