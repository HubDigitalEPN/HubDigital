<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarContenidoCaja;

/**
 * DTO de salida con un unit tray de la caja y su clasificación taxonómica dominante.
 */
final readonly class ConsultarContenidoCajaItemOutput
{
    /**
     * @param  ?array<string, ?string>  $clasificacionDominante  Niveles taxonómicos (orden…especie)
     *                                                           dominantes del unit tray, o null si está sin clasificar.
     */
    public function __construct(
        public string $unitTrayId,
        public int $numero,
        public ?array $clasificacionDominante,
    ) {}
}
