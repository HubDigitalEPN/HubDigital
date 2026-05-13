<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoEspecimen;

class Especimen
{
    private function __construct(
        private readonly EspecimenId $id,
        private readonly string $codigoCatalogo,
        private readonly string $taxonId,
        private string $localidad,
        private string $fechaColecta,
        private string $colector,
        private ?string $entidadDepositanteId,
        private EstadoEspecimen $estado,
    ) {}

    public static function crear(
        EspecimenId $id,
        string $codigoCatalogo,
        string $taxonId,
        string $localidad,
        string $fechaColecta,
        string $colector,
        ?string $entidadDepositanteId = null,
    ): self {
        return new self(
            id: $id,
            codigoCatalogo: trim($codigoCatalogo),
            taxonId: $taxonId,
            localidad: trim($localidad),
            fechaColecta: $fechaColecta,
            colector: trim($colector),
            entidadDepositanteId: $entidadDepositanteId,
            estado: EstadoEspecimen::Disponible,
        );
    }

    public static function reconstituir(
        EspecimenId $id,
        string $codigoCatalogo,
        string $taxonId,
        string $localidad,
        string $fechaColecta,
        string $colector,
        EstadoEspecimen $estado,
        ?string $entidadDepositanteId = null,
    ): self {
        return new self(
            id: $id,
            codigoCatalogo: $codigoCatalogo,
            taxonId: $taxonId,
            localidad: $localidad,
            fechaColecta: $fechaColecta,
            colector: $colector,
            entidadDepositanteId: $entidadDepositanteId,
            estado: $estado,
        );
    }

    public function actualizar(
        string $localidad,
        string $fechaColecta,
        string $colector,
        ?string $entidadDepositanteId,
    ): void {
        $this->localidad = trim($localidad);
        $this->fechaColecta = $fechaColecta;
        $this->colector = trim($colector);
        $this->entidadDepositanteId = $entidadDepositanteId;
    }

    public function marcarEnPrestamo(): void
    {
        $this->estado = EstadoEspecimen::EnPrestamo;
    }

    public function marcarDisponible(): void
    {
        $this->estado = EstadoEspecimen::Disponible;
    }

    public function id(): EspecimenId
    {
        return $this->id;
    }

    public function codigoCatalogo(): string
    {
        return $this->codigoCatalogo;
    }

    public function taxonId(): string
    {
        return $this->taxonId;
    }

    public function localidad(): string
    {
        return $this->localidad;
    }

    public function fechaColecta(): string
    {
        return $this->fechaColecta;
    }

    public function colector(): string
    {
        return $this->colector;
    }

    public function entidadDepositanteId(): ?string
    {
        return $this->entidadDepositanteId;
    }

    public function estado(): EstadoEspecimen
    {
        return $this->estado;
    }
}
