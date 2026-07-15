<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Adapters;

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\UbicacionGeocodificada;

/**
 * Traductor puro (sin red) de una respuesta `reverse` de Nominatim (formato
 * jsonv2 con `addressdetails=1`) a la {@see UbicacionGeocodificada} del dominio.
 *
 * Mapeo al vocabulario Darwin Core, con el matiz de Ecuador entre paréntesis:
 *  - country       ← address.country                       (país)
 *  - stateProvince ← address.state / region                (provincia)
 *  - municipality  ← address.county / city_district        (cantón)
 *  - poblado       ← primer lugar poblado disponible        (poblado/parroquia
 *                    city → town → village → hamlet → ...     más cercano)
 *
 * Se aísla como clase pura para poder probar el mapeo sin llamar a la API.
 */
final class MapeadorDireccionNominatim
{
    /**
     * @param  array<string, mixed>  $respuesta  Respuesta completa de /reverse (jsonv2).
     */
    public static function desdeRespuesta(array $respuesta): UbicacionGeocodificada
    {
        $direccion = is_array($respuesta['address'] ?? null) ? $respuesta['address'] : [];

        $primero = static function (array $claves) use ($direccion): ?string {
            foreach ($claves as $clave) {
                $valor = $direccion[$clave] ?? null;
                if (is_string($valor) && trim($valor) !== '') {
                    return trim($valor);
                }
            }

            return null;
        };

        return new UbicacionGeocodificada(
            country: $primero(['country']),
            stateProvince: $primero(['state', 'region', 'state_district']),
            municipality: $primero(['county', 'city_district', 'municipality']),
            poblado: $primero(['city', 'town', 'village', 'hamlet', 'suburb', 'neighbourhood', 'locality', 'municipality']),
            displayName: self::limpiar($respuesta['display_name'] ?? null),
        );
    }

    private static function limpiar(mixed $valor): ?string
    {
        return is_string($valor) && trim($valor) !== '' ? trim($valor) : null;
    }
}
