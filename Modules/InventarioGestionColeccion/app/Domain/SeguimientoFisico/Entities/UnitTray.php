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
        private CajaId $cajaId,
        private int $numero,
        private ?ClasificacionTaxonomica $clasificacionDominante,
    ) {}

    /**
     * Crea un UnitTray nuevo dentro de una caja, validando que su número de posición
     * sea positivo. La clasificación dominante es opcional y normalmente arranca nula
     * hasta que se le asignan especímenes.
     *
     * @throws \InvalidArgumentException Si el número es menor a 1.
     */
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

    /**
     * Rehidrata un UnitTray desde persistencia conservando su clasificación dominante
     * cacheada, sin revalidar el número.
     */
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

    /**
     * Reubica el tray a otra caja tomando el número que le corresponde en el destino:
     * la numeración es correlativa por caja, así que conservar la de origen chocaría
     * con las bandejas existentes. La reclasificación de las cajas origen/destino
     * la coordina la Application layer.
     *
     * @throws \InvalidArgumentException Si el número es menor a 1.
     */
    public function moverACaja(CajaId $cajaDestino, int $numeroEnDestino): void
    {
        if ($numeroEnDestino < 1) {
            throw new \InvalidArgumentException('El número de UnitTray debe ser mayor a 0.');
        }

        $this->cajaId = $cajaDestino;
        $this->numero = $numeroEnDestino;
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
