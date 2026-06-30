<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ReubicarEspecimenes;

/**
 * DTO de salida de la reubicación de especímenes.
 *
 * - `reubicado`: si la asignación se persistió.
 * - `requiereConfirmacion`: true cuando había advertencia taxonómica y no se confirmó, por lo
 *   que no se movió nada y el actor debe confirmar o cancelar.
 * - `especimenesFueraDeLugar`: códigos de catálogo que rompen el orden del tray destino.
 */
final readonly class ReubicarEspecimenesOutput
{
    /**
     * @param  string[]  $especimenesReubicados
     * @param  string[]  $especimenesFueraDeLugar
     */
    public function __construct(
        public bool $reubicado,
        public bool $requiereConfirmacion,
        public array $especimenesReubicados,
        public array $especimenesFueraDeLugar,
        public string $actorRol,
    ) {}
}
