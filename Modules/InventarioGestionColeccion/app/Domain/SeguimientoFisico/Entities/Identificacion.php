<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\IdentificacionId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TaxonId;

/**
 * Identificación — entrada en el historial taxonómico de un espécimen.
 *
 * Invariante de agregado: para un mismo `especimen_id` debe existir SOLO una
 * identificación con `es_actual = true`. La invariante se aplica a nivel del
 * caso de uso `IdentificarEspecimen`, que marca las demás como no-actuales
 * antes de persistir la nueva.
 */
class Identificacion
{
    private function __construct(
        private readonly IdentificacionId $id,
        private readonly EspecimenId $especimenId,
        private ?TaxonId $taxonId,
        private ?string $identificadoPor,
        private ?\DateTimeImmutable $fechaDeterminacion,
        private ?string $calificador,
        private ?string $observaciones,
        private bool $esActual,
    ) {}

    public static function crear(
        IdentificacionId $id,
        EspecimenId $especimenId,
        ?string $taxonId = null,
        ?string $identificadoPor = null,
        ?\DateTimeImmutable $fechaDeterminacion = null,
        ?string $calificador = null,
        ?string $observaciones = null,
        bool $esActual = true,
    ): self {
        return new self(
            id: $id,
            especimenId: $especimenId,
            taxonId: $taxonId !== null ? TaxonId::desde($taxonId) : null,
            identificadoPor: self::normalizarOpcional($identificadoPor),
            fechaDeterminacion: $fechaDeterminacion,
            calificador: self::normalizarOpcional($calificador),
            observaciones: self::normalizarOpcional($observaciones),
            esActual: $esActual,
        );
    }

    public static function reconstituir(
        IdentificacionId $id,
        EspecimenId $especimenId,
        ?TaxonId $taxonId,
        ?string $identificadoPor,
        ?\DateTimeImmutable $fechaDeterminacion,
        ?string $calificador,
        ?string $observaciones,
        bool $esActual,
    ): self {
        return new self(
            id: $id,
            especimenId: $especimenId,
            taxonId: $taxonId,
            identificadoPor: $identificadoPor,
            fechaDeterminacion: $fechaDeterminacion,
            calificador: $calificador,
            observaciones: $observaciones,
            esActual: $esActual,
        );
    }

    public function marcarComoSuperseded(): void
    {
        $this->esActual = false;
    }

    public function marcarComoActual(): void
    {
        $this->esActual = true;
    }

    private static function normalizarOpcional(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }
        $trim = trim($valor);

        return $trim === '' ? null : $trim;
    }

    public function id(): IdentificacionId
    {
        return $this->id;
    }

    public function especimenId(): EspecimenId
    {
        return $this->especimenId;
    }

    public function taxonId(): ?TaxonId
    {
        return $this->taxonId;
    }

    public function identificadoPor(): ?string
    {
        return $this->identificadoPor;
    }

    public function fechaDeterminacion(): ?\DateTimeImmutable
    {
        return $this->fechaDeterminacion;
    }

    public function calificador(): ?string
    {
        return $this->calificador;
    }

    public function observaciones(): ?string
    {
        return $this->observaciones;
    }

    public function esActual(): bool
    {
        return $this->esActual;
    }
}
