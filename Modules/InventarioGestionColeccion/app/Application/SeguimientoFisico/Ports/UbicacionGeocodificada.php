<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports;

/**
 * Resultado inmutable de una geocodificación inversa.
 *
 * Los campos siguen el vocabulario Darwin Core que usa el catálogo:
 *  - country        → país
 *  - stateProvince  → provincia
 *  - municipality   → cantón / municipio
 *  - poblado        → localidad poblada más cercana (ciudad, pueblo, parroquia)
 *  - displayName    → cadena legible completa devuelta por el servicio
 *
 * Cualquier campo puede ser null si el servicio no lo resolvió.
 */
final readonly class UbicacionGeocodificada
{
    public function __construct(
        public ?string $country = null,
        public ?string $stateProvince = null,
        public ?string $municipality = null,
        public ?string $poblado = null,
        public ?string $displayName = null,
    ) {}
}
