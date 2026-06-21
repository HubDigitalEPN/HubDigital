<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\LocalizarEspecimenParaVisitante;

/**
 * Resultado de la guía pública de ubicación: la ruta física hasta el espécimen
 * que el visitante busca por nombre científico.
 *
 * La visibilidad NO depende de ningún flag: la ubicación siempre es compartible.
 * `disponiblePublicamente` es false únicamente cuando el espécimen no está
 * colocado en un unit tray (no tiene custodia física resolvable), en cuyo caso
 * la ruta queda en null y la UI informa "no disponible públicamente".
 */
final readonly class LocalizarEspecimenParaVisitanteOutput
{
    public function __construct(
        public bool $encontrado,
        public bool $disponiblePublicamente,
        public int $coincidencias = 0,
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
