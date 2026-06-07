<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\LocalizarEspecimen;

/**
 * Ruta física hasta un espécimen para la navegación del mapa (búsqueda → zoom).
 * Los identificadores de gabinete, caja y unit tray permiten al componente abrir
 * cada nivel de forma perezosa. Los niveles pueden ser null si la caja está fuera
 * de su ranura (la ruta llega solo hasta donde el espécimen es rastreable).
 */
final readonly class LocalizarEspecimenOutput
{
    public function __construct(
        public bool $encontrado,
        public bool $ubicado,
        public ?string $especimenId = null,
        public ?string $codigoCatalogo = null,
        public ?string $nombreCientifico = null,
        public ?string $gabineteId = null,
        public ?string $gabineteCodigo = null,
        public ?string $gabineteNombre = null,
        public ?string $ranuraId = null,
        public ?int $numeroRanura = null,
        public ?string $cajaId = null,
        public ?string $codigoCaja = null,
        public ?string $unitTrayId = null,
        public ?int $numeroUnitTray = null,
    ) {}
}
