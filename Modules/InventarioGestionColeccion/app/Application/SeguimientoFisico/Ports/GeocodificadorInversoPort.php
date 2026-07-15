<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports;

/**
 * Puerto de geocodificación inversa: a partir de una coordenada (lat/long)
 * resuelve la ubicación administrativa (país, provincia, cantón) y el poblado
 * más cercano. La implementación concreta (Nominatim/OSM, Google, etc.) vive en
 * infraestructura; la capa de presentación solo depende de esta interfaz.
 */
interface GeocodificadorInversoPort
{
    /**
     * Devuelve la ubicación para la coordenada dada, o null si el servicio no
     * responde o no encuentra nada (zonas remotas, sin cobertura).
     */
    public function geocodificar(float $latitud, float $longitud): ?UbicacionGeocodificada;
}
