<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarOcupacionGabinete;

/**
 * DTO de salida con el estado de ocupación de una ranura y la caja que la ocupa, si la hay.
 */
final readonly class ConsultarOcupacionGabineteItemOutput
{
    /**
     * @param  ?array<string, ?string>  $clasificacion  Niveles taxonómicos (orden…especie) de la caja
     *                                                  que ocupa la ranura, o null si está vacía/sin clasificar.
     */
    public function __construct(
        public string $ranuraId,
        public int $numeroRanura,
        public bool $ocupada,
        public ?string $cajaId,
        public ?string $codigoCaja,
        public ?string $estado,
        public bool $esEspecial,
        public ?array $clasificacion,
    ) {}
}
