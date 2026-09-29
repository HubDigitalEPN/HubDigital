<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** Índices de ejemplares identificados a especie y con conteo individual positivo. */
final class CalcularDiversidadColeccion
{
    public const METRICAS = [
        'shannon' => 'Shannon-Wiener H′',
        'simpson' => 'Simpson D',
        'margalef' => 'Margalef',
        'menhinick' => 'Menhinick',
        'pielou' => 'Equidad de Pielou J′',
        'alfa' => 'Diversidad alfa',
        'beta' => 'Diversidad beta (Sørensen)',
        'gamma' => 'Diversidad gamma',
        'abundancia' => 'Abundancia absoluta y relativa',
        'acumulacion' => 'Acumulación observada de especies',
        'chao1' => 'Chao1',
        'jackknife' => 'Jackknife de primer orden',
    ];

    private function consulta(array $filtros, ?string $localidad = null): Builder
    {
        $query = DB::table('taxonomia.especimenes as e')
            ->join('taxonomia.taxones as t', 't.id', '=', 'e.taxon_id')
            ->where('t.rango', 'especie')
            ->where('e.individual_count', '>', 0);
        if (! empty($filtros['provincia'])) {
            $query->where('e.state_province', $filtros['provincia']);
        }
        if (! empty($filtros['desde'])) {
            $query->whereYear('e.fecha_colecta', '>=', (int) $filtros['desde']);
        }
        if (! empty($filtros['hasta'])) {
            $query->whereYear('e.fecha_colecta', '<=', (int) $filtros['hasta']);
        }
        if ($localidad !== null) {
            $query->where('e.localidad_id', $localidad);
        }

        return $query;
    }

    public function ejecutar(array $metricas, array $filtros): array
    {
        $sitioA = $filtros['sitio_a'] ?? null;
        $sitioB = $filtros['sitio_b'] ?? null;
        $base = $this->consulta($filtros, $sitioA);
        $filas = (clone $base)
            ->selectRaw('t.id AS taxon_id, t.nombre_cientifico AS especie, SUM(e.individual_count)::bigint AS individuos, COUNT(*) AS registros')
            ->groupBy('t.id', 't.nombre_cientifico')->orderByDesc('individuos')->get();
        $s = $filas->count();
        $n = (int) $filas->sum('individuos');
        $registros = (int) $filas->sum('registros');
        $valores = [];

        if ($n > 0) {
            $h = 0.0;
            $simpsonNumerador = 0.0;
            $unicos = 0;
            $dobles = 0;
            foreach ($filas as $fila) {
                $ni = (int) $fila->individuos;
                $p = $ni / $n;
                $h -= $p * log($p);
                $simpsonNumerador += $ni * ($ni - 1);
                $unicos += (int) ($ni === 1);
                $dobles += (int) ($ni === 2);
            }
            $formulas = [
                'shannon' => $h,
                'simpson' => $n > 1 ? $simpsonNumerador / ($n * ($n - 1)) : null,
                'margalef' => $n > 1 ? ($s - 1) / log($n) : null,
                'menhinick' => $s / sqrt($n),
                'pielou' => $s > 1 ? $h / log($s) : null,
                'alfa' => $s,
                'chao1' => $s + ($dobles > 0 ? $unicos * $unicos / (2 * $dobles) : $unicos * ($unicos - 1) / 2),
            ];
            foreach ($metricas as $metrica) {
                if (array_key_exists($metrica, $formulas)) {
                    $valores[$metrica] = $formulas[$metrica];
                }
            }
        }

        if (in_array('abundancia', $metricas, true)) {
            $valores['abundancia'] = $filas->take(30)->map(static fn ($fila) => [
                'especie' => $fila->especie,
                'individuos' => (int) $fila->individuos,
                'porcentaje' => $n > 0 ? 100 * (int) $fila->individuos / $n : 0,
            ])->all();
        }

        if (in_array('gamma', $metricas, true)) {
            $valores['gamma'] = $s;
        }
        if (in_array('beta', $metricas, true)) {
            $valores['beta'] = null;
        }
        if (($sitioA && $sitioB && $sitioA !== $sitioB)
            && (in_array('beta', $metricas, true) || in_array('gamma', $metricas, true))) {
            $a = array_fill_keys($filas->pluck('taxon_id')->all(), true);
            $b = array_fill_keys($this->consulta($filtros, $sitioB)->distinct()->pluck('t.id')->all(), true);
            $compartidas = count(array_intersect_key($a, $b));
            $soloA = count($a) - $compartidas;
            $soloB = count($b) - $compartidas;
            $denominador = 2 * $compartidas + $soloA + $soloB;
            if (in_array('beta', $metricas, true)) {
                $valores['beta'] = $denominador > 0 ? ($soloA + $soloB) / $denominador : null;
            }
            if (in_array('gamma', $metricas, true)) {
                $valores['gamma'] = count($a + $b);
            }
        }

        if (in_array('acumulacion', $metricas, true) || in_array('jackknife', $metricas, true)) {
            $incidencias = (clone $base)->whereNotNull('e.muestra_id')
                ->select('e.muestra_id', 't.id AS taxon_id')->distinct()
                ->orderBy('e.muestra_id')->orderBy('t.id')->cursor();
            $frecuencias = [];
            $puntos = [];
            $muestraActual = null;
            $muestras = 0;
            foreach ($incidencias as $incidencia) {
                if ($muestraActual !== $incidencia->muestra_id) {
                    if ($muestras > 0 && ($muestras <= 60 || $muestras % 100 === 0)) {
                        $puntos[] = ['muestras' => $muestras, 'especies' => count($frecuencias)];
                    }
                    $muestraActual = $incidencia->muestra_id;
                    $muestras++;
                }
                $frecuencias[$incidencia->taxon_id] = ($frecuencias[$incidencia->taxon_id] ?? 0) + 1;
            }
            if ($muestras > 0) {
                $puntos[] = ['muestras' => $muestras, 'especies' => count($frecuencias)];
            }
            if (in_array('acumulacion', $metricas, true)) {
                $valores['acumulacion'] = $puntos;
            }
            if (in_array('jackknife', $metricas, true)) {
                $q1 = count(array_filter($frecuencias, static fn ($frecuencia) => $frecuencia === 1));
                $valores['jackknife'] = $muestras > 1 ? count($frecuencias) + $q1 * ($muestras - 1) / $muestras : null;
            }
        }

        return [
            'valores' => $valores,
            'individuos' => $n,
            'especies' => $s,
            'registros' => $registros,
            'sitio_a' => $sitioA,
            'sitio_b' => $sitioB,
        ];
    }
}
