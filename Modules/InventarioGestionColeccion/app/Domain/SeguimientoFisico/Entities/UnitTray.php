<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\ClasificacionTaxonomica;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\UnitTrayId;

/**
 * Bandeja interna de una Caja que agrupa especímenes físicamente.
 *
 * La clasificación taxonómica no se ingresa directamente en el UnitTray,
 * sino que se propaga desde los Especímenes que contiene. La Application layer
 * computa la clasificación dominante y la actualiza vía actualizarClasificacion().
 *
 * A su vez, UnitTray propaga su clasificación hacia Caja, que la cachea
 * para permitir la evaluación de orden taxonómico entre cajas vecinas.
 */
class UnitTray
{
    private function __construct(
        private readonly UnitTrayId $id,
        private readonly CajaId $cajaId,
        private readonly int $numero,
        private ?ClasificacionTaxonomica $clasificacionDominante,
    ) {}

    public static function crear(
        UnitTrayId $id,
        CajaId $cajaId,
        int $numero,
        ?ClasificacionTaxonomica $clasificacionDominante = null,
    ): self {
        if ($numero < 1) {
            throw new \InvalidArgumentException('El número de UnitTray debe ser mayor a 0.');
        }

        return new self(
            id: $id,
            cajaId: $cajaId,
            numero: $numero,
            clasificacionDominante: $clasificacionDominante,
        );
    }

    public static function reconstituir(
        UnitTrayId $id,
        CajaId $cajaId,
        int $numero,
        ?ClasificacionTaxonomica $clasificacionDominante,
    ): self {
        return new self(
            id: $id,
            cajaId: $cajaId,
            numero: $numero,
            clasificacionDominante: $clasificacionDominante,
        );
    }

    /**
     * Actualiza la clasificación dominante del tray cuando sus especímenes cambian.
     * La Application layer calcula la clasificación dominante y la pasa aquí.
     */
    public function actualizarClasificacion(ClasificacionTaxonomica $clasificacion): void
    {
        $this->clasificacionDominante = $clasificacion;
    }

    /**
     * Limpia la clasificación cuando el tray queda sin especímenes.
     */
    public function limpiarClasificacion(): void
    {
        $this->clasificacionDominante = null;
    }

    public function id(): UnitTrayId
    {
        return $this->id;
    }

    public function cajaId(): CajaId
    {
        return $this->cajaId;
    }

    public function numero(): int
    {
        return $this->numero;
    }

    public function clasificacionDominante(): ?ClasificacionTaxonomica
    {
        return $this->clasificacionDominante;
    }
}
