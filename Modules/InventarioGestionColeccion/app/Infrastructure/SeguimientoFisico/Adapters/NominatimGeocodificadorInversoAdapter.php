<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Adapters;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\GeocodificadorInversoPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\UbicacionGeocodificada;

/**
 * Geocodificador inverso sobre Nominatim (OpenStreetMap).
 *
 * Consideraciones de la política de uso de Nominatim (nominatim.org/release-docs):
 *  - Se envía un `User-Agent` identificable (obligatorio) configurable por env.
 *  - Se cachea por coordenada redondeada (5 decimales ≈ 1 m) durante ~30 días:
 *    las fronteras administrativas no cambian y muchos especímenes comparten el
 *    mismo sitio de colecta, así que se consulta cada punto una sola vez.
 *  - Fallo silencioso: si el servicio no responde o no encuentra nada, devuelve
 *    null (la UI muestra un aviso), nunca revienta el flujo.
 *
 * NOTA: el endpoint público NO admite geocodificación masiva. Para completar
 * lotes grandes conviene throttling y deduplicar por coordenada (ver el uso en
 * la importación), o un Nominatim propio.
 *
 * Implementa {@see GeocodificadorInversoPort}.
 */
final class NominatimGeocodificadorInversoAdapter implements GeocodificadorInversoPort
{
    public function geocodificar(float $latitud, float $longitud): ?UbicacionGeocodificada
    {
        $lat = round($latitud, 5);
        $lon = round($longitud, 5);
        $dias = max(1, (int) config('inventariogestioncoleccion.nominatim.cache_days', 30));

        $cacheado = Cache::get($this->claveCache($lat, $lon));
        if ($cacheado instanceof UbicacionGeocodificada) {
            return $cacheado;
        }

        $ubicacion = $this->consultar($lat, $lon);

        if ($ubicacion !== null) {
            Cache::put($this->claveCache($lat, $lon), $ubicacion, now()->addDays($dias));
        }

        return $ubicacion;
    }

    private function consultar(float $lat, float $lon): ?UbicacionGeocodificada
    {
        try {
            $baseUrl = rtrim((string) config('inventariogestioncoleccion.nominatim.base_url', 'https://nominatim.openstreetmap.org'), '/');

            $response = Http::timeout((int) config('inventariogestioncoleccion.nominatim.timeout', 10))
                ->withHeaders([
                    'User-Agent' => (string) config('inventariogestioncoleccion.nominatim.user_agent', 'HubDigital/1.0'),
                    'Accept-Language' => 'es',
                ])
                ->get("{$baseUrl}/reverse", [
                    'format' => 'jsonv2',
                    'lat' => $lat,
                    'lon' => $lon,
                    'zoom' => 12,
                    'addressdetails' => 1,
                ]);

            if ($response->failed()) {
                Log::warning('Nominatim: respuesta fallida', ['status' => $response->status(), 'lat' => $lat, 'lon' => $lon]);

                return null;
            }

            $json = $response->json();
            if (! is_array($json) || isset($json['error']) || ! isset($json['address'])) {
                return null;
            }

            $ubicacion = MapeadorDireccionNominatim::desdeRespuesta($json);

            // Sin ningún campo resuelto → trátalo como "sin resultado".
            if ($ubicacion->country === null && $ubicacion->stateProvince === null
                && $ubicacion->municipality === null && $ubicacion->poblado === null) {
                return null;
            }

            return $ubicacion;
        } catch (\Throwable $e) {
            Log::warning('Nominatim: excepción', ['error' => $e->getMessage(), 'lat' => $lat, 'lon' => $lon]);

            return null;
        }
    }

    private function claveCache(float $lat, float $lon): string
    {
        return "nominatim_reverse:{$lat},{$lon}";
    }
}
