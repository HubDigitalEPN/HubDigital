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
     * Códigos ISO 3166-2 de las provincias de Ecuador → nombre. Se usa como
     * respaldo cuando Nominatim no devuelve `state` (p. ej. el Distrito
     * Metropolitano de Quito, donde la provincia solo llega como `EC-P`).
     */
    private const PROVINCIAS_EC = [
        'EC-A' => 'Azuay', 'EC-B' => 'Bolívar', 'EC-C' => 'Carchi', 'EC-D' => 'Orellana',
        'EC-E' => 'Esmeraldas', 'EC-F' => 'Cañar', 'EC-G' => 'Guayas', 'EC-H' => 'Chimborazo',
        'EC-I' => 'Imbabura', 'EC-L' => 'Loja', 'EC-M' => 'Manabí', 'EC-N' => 'Napo',
        'EC-O' => 'El Oro', 'EC-P' => 'Pichincha', 'EC-R' => 'Los Ríos', 'EC-S' => 'Morona Santiago',
        'EC-SD' => 'Santo Domingo de los Tsáchilas', 'EC-SE' => 'Santa Elena', 'EC-T' => 'Tungurahua',
        'EC-U' => 'Sucumbíos', 'EC-W' => 'Galápagos', 'EC-X' => 'Cotopaxi', 'EC-Y' => 'Pastaza',
        'EC-Z' => 'Zamora Chinchipe',
    ];

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

        // Provincia: primero los campos administrativos; si faltan, se resuelve
        // desde el código ISO 3166-2 (útil para cantones metropolitanos).
        $stateProvince = $primero(['state', 'region', 'state_district'])
            ?? self::provinciaDesdeIso($direccion['ISO3166-2-lvl4'] ?? null);

        return new UbicacionGeocodificada(
            country: $primero(['country']),
            stateProvince: $stateProvince,
            municipality: $primero(['county', 'city_district', 'municipality']),
            poblado: $primero(['city', 'town', 'village', 'hamlet', 'suburb', 'neighbourhood', 'locality', 'municipality']),
            displayName: self::limpiar($respuesta['display_name'] ?? null),
        );
    }

    private static function provinciaDesdeIso(mixed $codigo): ?string
    {
        if (! is_string($codigo)) {
            return null;
        }

        return self::PROVINCIAS_EC[strtoupper(trim($codigo))] ?? null;
    }

    private static function limpiar(mixed $valor): ?string
    {
        return is_string($valor) && trim($valor) !== '' ? trim($valor) : null;
    }
}
