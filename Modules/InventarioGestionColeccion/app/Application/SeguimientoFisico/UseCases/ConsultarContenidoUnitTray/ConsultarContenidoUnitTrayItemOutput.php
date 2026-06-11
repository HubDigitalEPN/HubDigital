<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarContenidoUnitTray;

/**
 * DTO de salida con un espécimen del unit tray (código de catálogo y nombre científico).
 */
final readonly class ConsultarContenidoUnitTrayItemOutput
{
    public function __construct(
        public string $especimenId,
        public string $codigoCatalogo,
        public ?string $nombreCientifico,
    ) {}
}
