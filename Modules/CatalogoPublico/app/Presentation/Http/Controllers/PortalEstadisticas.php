<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Presentation\Http\Controllers;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PortalEstadisticas
{
    public function __invoke(Request $request): View
    {
        $filtros = $request->validate([
            'provincia' => ['nullable', 'string', 'max:120'],
            'desde' => ['nullable', 'integer', 'between:1800,2100'],
            'hasta' => ['nullable', 'integer', 'between:1800,2100'],
        ]);
        $this->validarPeriodo($filtros);
        $filtros = array_filter($filtros, static fn ($valor) => $valor !== null && $valor !== '');
        $provincias = $this->cachear('portal:provincias:v4', static fn () => DB::table('taxonomia.especimenes as e')
            ->join('divulgacion.especimenes_divulgables as d', 'd.especimen_id', '=', 'e.id')
            ->where('d.publicado', true)
            ->where('d.state_province_visible', true)
            ->whereNotNull('e.state_province')
            ->where('e.state_province', '<>', '')
            ->distinct()->orderBy('e.state_province')->pluck('e.state_province')->all());

        if (isset($filtros['provincia']) && ! in_array($filtros['provincia'], $provincias, true)) {
            $filtros['provincia'] = '';
        }

        $datos = $this->cachear('portal:estadisticas:v4:'.sha1(json_encode($filtros)), fn () => $this->resumir($filtros));

        return view('catalogopublico::portal-estadisticas', compact('datos', 'filtros', 'provincias'));
    }

    public function descargarLista(Request $request): StreamedResponse
    {
        $filtros = $request->validate([
            'provincia' => ['nullable', 'string', 'max:120'],
            'desde' => ['nullable', 'integer', 'between:1800,2100'],
            'hasta' => ['nullable', 'integer', 'between:1800,2100'],
        ]);
        $this->validarPeriodo($filtros);
        $consulta = $this->consulta($filtros)
            ->join('taxonomia.taxones as t', 't.id', '=', 'e.taxon_id')
            ->where('t.rango', 'especie')->where('d.scientific_name_visible', true)
            ->selectRaw('t.nombre_cientifico AS especie, COUNT(*) AS registros')
            ->groupBy('t.nombre_cientifico')->orderBy('t.nombre_cientifico');

        return response()->streamDownload(static function () use ($consulta): void {
            $salida = fopen('php://output', 'wb');
            fwrite($salida, "\xEF\xBB\xBF");
            fputcsv($salida, ['Especie', 'Registros'], ';', '"', '');
            foreach ($consulta->cursor() as $fila) {
                $nombre = (string) $fila->especie;
                if (preg_match('/^[=+\-@]/', $nombre)) {
                    $nombre = "'".$nombre;
                }
                fputcsv($salida, [$nombre, (int) $fila->registros], ';', '"', '');
            }
            fclose($salida);
        }, 'especies-coleccion.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function consulta(array $filtros): Builder
    {
        $query = DB::table('taxonomia.especimenes as e')
            ->join('divulgacion.especimenes_divulgables as d', 'd.especimen_id', '=', 'e.id')
            ->where('d.publicado', true);
        if (! empty($filtros['provincia'])) {
            $query->where('d.state_province_visible', true)->where('e.state_province', $filtros['provincia']);
        }
        if (isset($filtros['desde'])) {
            $query->whereYear('e.fecha_colecta', '>=', (int) $filtros['desde']);
        }
        if (isset($filtros['hasta'])) {
            $query->whereYear('e.fecha_colecta', '<=', (int) $filtros['hasta']);
        }

        return $query;
    }

    private function validarPeriodo(array $filtros): void
    {
        if (isset($filtros['desde'], $filtros['hasta']) && (int) $filtros['desde'] > (int) $filtros['hasta']) {
            throw ValidationException::withMessages(['hasta' => 'El año final debe ser igual o posterior al inicial.']);
        }
    }

    /** El panel sigue disponible si falla el almacén de caché; los errores SQL sí se propagan. */
    private function cachear(string $clave, callable $calcular): array
    {
        try {
            $guardado = Cache::get($clave);
            if (is_array($guardado)) {
                return $guardado;
            }
        } catch (\Throwable $error) {
            Log::warning('Caché de estadísticas no disponible', ['operacion' => 'leer', 'tipo' => $error::class]);
        }

        $resultado = $calcular();
        try {
            Cache::put($clave, $resultado, 300);
        } catch (\Throwable $error) {
            Log::warning('Caché de estadísticas no disponible', ['operacion' => 'guardar', 'tipo' => $error::class]);
        }

        return $resultado;
    }

    private function resumir(array $filtros): array
    {
        $base = $this->consulta($filtros);
        $resumen = (array) (clone $base)->leftJoin('taxonomia.taxones as t', 't.id', '=', 'e.taxon_id')
            ->selectRaw("COUNT(*) AS registros, COUNT(*) FILTER (WHERE t.rango = 'especie' AND d.scientific_name_visible) AS identificados, COUNT(*) FILTER (WHERE e.fecha_colecta IS NOT NULL AND d.event_date_visible) AS fechados, COUNT(*) FILTER (WHERE e.decimal_latitude IS NOT NULL AND e.decimal_longitude IS NOT NULL AND d.decimal_latitude_visible AND d.decimal_longitude_visible) AS georreferenciados")
            ->first();

        $porTaxon = (clone $base)->selectRaw('e.taxon_id, COUNT(*) AS total')
            ->groupBy('e.taxon_id')->get();
        $taxones = DB::table('taxonomia.taxones')->select('id', 'padre_id', 'rango', 'nombre_cientifico')
            ->get()->keyBy('id');
        $filos = [];
        foreach ($porTaxon as $fila) {
            $id = $fila->taxon_id;
            $filo = 'Sin filo';
            for ($paso = 0; $paso < 20 && $id && isset($taxones[$id]); $paso++) {
                $taxon = $taxones[$id];
                if ($taxon->rango === 'phylum') {
                    $filo = $taxon->nombre_cientifico;
                    break;
                }
                $id = $taxon->padre_id;
            }
            $filos[$filo] = ($filos[$filo] ?? 0) + (int) $fila->total;
        }
        arsort($filos);

        $anios = (clone $base)->whereNotNull('e.fecha_colecta')->where('d.event_date_visible', true)
            ->selectRaw('EXTRACT(YEAR FROM e.fecha_colecta)::integer AS anio, COUNT(*) AS total')
            ->groupByRaw('1')->orderBy('anio')->get()->map(static fn (object $fila): array => (array) $fila)->all();
        $provincias = (clone $base)->where('d.state_province_visible', true)
            ->whereNotNull('e.state_province')->selectRaw('e.state_province AS provincia, COUNT(*) AS total')
            ->groupBy('e.state_province')->orderByDesc('total')->limit(12)->get()->map(static fn (object $fila): array => (array) $fila)->all();
        $especies = (clone $base)->join('taxonomia.taxones as t', 't.id', '=', 'e.taxon_id')
            ->where('t.rango', 'especie')->where('d.scientific_name_visible', true)
            ->selectRaw('t.nombre_cientifico AS nombre, COUNT(*) AS total')
            ->groupBy('t.nombre_cientifico')->orderByDesc('total')->limit(20)->get()->map(static fn (object $fila): array => (array) $fila)->all();

        // Celdas de 0,25 grados: el navegador recibe centenares de círculos,
        // nunca las decenas de miles de coordenadas individuales.
        $mapa = (clone $base)->where('d.decimal_latitude_visible', true)
            ->where('d.decimal_longitude_visible', true)
            ->whereNotNull('e.decimal_latitude')->whereNotNull('e.decimal_longitude')
            ->selectRaw('ROUND((e.decimal_latitude * 4)::numeric) / 4 AS lat, ROUND((e.decimal_longitude * 4)::numeric) / 4 AS lon, COUNT(*) AS total')
            ->groupByRaw('1, 2')->orderByDesc('total')->limit(800)->get()->map(static fn (object $fila): array => (array) $fila)->all();

        return [
            'resumen' => $resumen,
            'filos' => $filos,
            'anios' => $anios,
            'provincias' => $provincias,
            'especies' => $especies,
            'mapa' => $mapa,
        ];
    }
}
