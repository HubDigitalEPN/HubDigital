<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarEspecimenesUnitTray;

/**
 * DTO de salida con la clasificación resultante del tray y los especímenes detectados fuera de lugar.
 */
final readonly class ActualizarEspecimenesUnitTrayOutput
{
    /**
     * @param  string[]  $especimenesFueraDeLugar  códigos de catálogo de especímenes
     *                                             cuya taxonomía no coincide con el tray
     */
    private function __construct(
        public string $unitTrayId,
        public bool $tieneClasificacion,
        public ?string $subfamiliaAsignada,
        public ?string $generoAsignado,
        public array $especimenesFueraDeLugar = [],
    ) {}

    public static function fromPrimitives(array $data): self
    {
        return new self(
            unitTrayId: $data['unitTrayId'],
            tieneClasificacion: $data['tieneClasificacion'],
            subfamiliaAsignada: $data['subfamiliaAsignada'],
            generoAsignado: $data['generoAsignado'],
            especimenesFueraDeLugar: $data['especimenesFueraDeLugar'] ?? [],
        );
    }
}
