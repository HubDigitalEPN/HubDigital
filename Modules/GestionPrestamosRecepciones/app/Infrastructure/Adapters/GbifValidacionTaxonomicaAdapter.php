<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Adapters;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\GestionPrestamosRecepciones\Application\Ports\ValidacionTaxonomicaPort;

/**
 * Adapter que consulta la API Species Match de GBIF para validar
 * nombres científicos contra la taxonomía backbone mundial.
 *
 * Optimizaciones:
 * - Deduplicación de nombres antes de consultar
 * - Cache de 7 días por especie (la taxonomía no cambia frecuentemente)
 * - Solicitudes concurrentes en batches de 10 vía Http::pool()
 * - Umbral de confianza >= 85 % para sugerencias tipográficas
 * - Transparencia en fallos: retorna 'no_verificado' en vez de simular 'catalogado'
 *
 * @see https://techdocs.gbif.org/en/openapi/v1/species
 */
final class GbifValidacionTaxonomicaAdapter implements ValidacionTaxonomicaPort
{
    private const BASE_URL = 'https://api.gbif.org/v1/species/match';

    private const BATCH_SIZE = 10;

    private const CACHE_TTL_DAYS = 7;

    private const CONFIDENCE_THRESHOLD = 85;

    private const CACHE_PREFIX = 'gbif_species:';

    public function validarEspecies(array $nombresCientificos): array
    {
        $nombresUnicos = array_values(array_unique($nombresCientificos));

        $resultadosCache = [];
        $nombresPendientes = [];

        foreach ($nombresUnicos as $nombre) {
            $cached = Cache::get(self::CACHE_PREFIX.md5($nombre));
            if ($cached !== null) {
                $resultadosCache[$nombre] = $cached;
            } else {
                $nombresPendientes[] = $nombre;
            }
        }

        $resultadosApi = [];

        foreach (array_chunk($nombresPendientes, self::BATCH_SIZE) as $batch) {
            $resultadosBatch = $this->consultarBatch($batch);

            foreach ($resultadosBatch as $nombre => $resultado) {
                $resultadosApi[$nombre] = $resultado;

                if ($resultado['estado'] !== 'no_verificado') {
                    Cache::put(
                        self::CACHE_PREFIX.md5($nombre),
                        $resultado,
                        now()->addDays(self::CACHE_TTL_DAYS)
                    );
                }
            }
        }

        $todosResultados = array_merge($resultadosCache, $resultadosApi);

        $resultados = [];

        foreach ($nombresCientificos as $nombre) {
            $resultados[] = $todosResultados[$nombre] ?? $this->resultadoNoVerificado($nombre);
        }

        return $resultados;
    }

    /**
     * @param  string[]  $nombres
     * @return array<string, array{nombreCientifico: string, estado: string, sugerencia: ?string}>
     */
    private function consultarBatch(array $nombres): array
    {
        $resultados = [];
        $claves = [];

        foreach ($nombres as $nombre) {
            $claves[md5($nombre)] = $nombre;
        }

        try {
            $responses = Http::pool(fn (Pool $pool) => array_map(
                fn (string $nombre) => $pool->as(md5($nombre))
                    ->timeout(10)
                    ->get(self::BASE_URL, [
                        'name' => $nombre,
                        'verbose' => 'false',
                        'kingdom' => 'Animalia',
                    ]),
                $nombres
            ));

            foreach ($claves as $clave => $nombre) {
                $response = $responses[$clave] ?? null;

                if (! $response instanceof Response || $response->failed()) {
                    Log::warning('GBIF API: respuesta fallida', [
                        'especie' => $nombre,
                        'status' => $response instanceof Response ? $response->status() : 'sin_respuesta',
                    ]);
                    $resultados[$nombre] = $this->resultadoNoVerificado($nombre);

                    continue;
                }

                $resultados[$nombre] = $this->interpretarRespuesta($nombre, $response->json());
            }
        } catch (\Throwable $e) {
            Log::warning('GBIF API: excepción en batch', ['error' => $e->getMessage()]);

            foreach ($nombres as $nombre) {
                if (! isset($resultados[$nombre])) {
                    $resultados[$nombre] = $this->resultadoNoVerificado($nombre);
                }
            }
        }

        return $resultados;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{nombreCientifico: string, estado: string, sugerencia: ?string}
     */
    private function interpretarRespuesta(string $nombreCientifico, array $data): array
    {
        $matchType = $data['matchType'] ?? 'NONE';
        $confidence = (int) ($data['confidence'] ?? 0);
        $canonicalName = $data['canonicalName'] ?? null;

        if ($matchType === 'NONE') {
            return [
                'nombreCientifico' => $nombreCientifico,
                'estado' => 'no_catalogado',
                'sugerencia' => null,
            ];
        }

        if ($matchType === 'HIGHERRANK') {
            return [
                'nombreCientifico' => $nombreCientifico,
                'estado' => 'no_catalogado',
                'sugerencia' => null,
            ];
        }

        if ($matchType === 'FUZZY' && $canonicalName !== null) {
            if ($confidence >= self::CONFIDENCE_THRESHOLD) {
                return [
                    'nombreCientifico' => $nombreCientifico,
                    'estado' => 'inconsistencia_tipografica',
                    'sugerencia' => $canonicalName,
                ];
            }

            return [
                'nombreCientifico' => $nombreCientifico,
                'estado' => 'no_catalogado',
                'sugerencia' => null,
            ];
        }

        return $this->resultadoCatalogado($nombreCientifico);
    }

    /**
     * @return array{nombreCientifico: string, estado: string, sugerencia: ?string}
     */
    private function resultadoCatalogado(string $nombreCientifico): array
    {
        return [
            'nombreCientifico' => $nombreCientifico,
            'estado' => 'catalogado',
            'sugerencia' => null,
        ];
    }

    /**
     * @return array{nombreCientifico: string, estado: string, sugerencia: ?string}
     */
    private function resultadoNoVerificado(string $nombreCientifico): array
    {
        return [
            'nombreCientifico' => $nombreCientifico,
            'estado' => 'no_verificado',
            'sugerencia' => null,
        ];
    }
}
