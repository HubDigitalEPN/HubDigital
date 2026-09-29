<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Presentation\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class CompararEspecies
{
    public function __invoke(Request $request): View
    {
        $especies = Cache::remember('portal:especies-comparables:v1', 300, static fn (): array => DB::table('taxonomia.especimenes as e')
            ->join('divulgacion.especimenes_divulgables as d', 'd.especimen_id', '=', 'e.id')
            ->join('taxonomia.taxones as t', 't.id', '=', 'e.taxon_id')
            ->where('t.rango', 'especie')
            ->where('d.scientific_name_visible', true)
            ->where('d.genus_visible', true)
            ->distinct()->orderBy('t.nombre_cientifico')->pluck('t.nombre_cientifico')->all());

        $seleccion = $request->validate([
            'a' => ['nullable', 'string', Rule::in($especies)],
            'b' => ['nullable', 'string', Rule::in($especies)],
        ]);
        $a = $seleccion['a'] ?? '';
        $b = $seleccion['b'] ?? '';
        $datos = [];

        if ($a !== '' && $b !== '' && $a !== $b) {
            $datos = Cache::remember('portal:comparacion:v1:'.sha1($a.'|'.$b), 300, static fn (): array => DB::table('taxonomia.especimenes as e')
                ->join('divulgacion.especimenes_divulgables as d', 'd.especimen_id', '=', 'e.id')
                ->join('taxonomia.taxones as t', 't.id', '=', 'e.taxon_id')
                ->where('t.rango', 'especie')
                ->where('d.scientific_name_visible', true)
                ->where('d.genus_visible', true)
                ->whereIn('t.nombre_cientifico', [$a, $b])
                ->selectRaw('t.nombre_cientifico AS especie, COUNT(*) AS registros')
                ->selectRaw('COUNT(DISTINCT e.state_province) FILTER (WHERE d.state_province_visible) AS provincias')
                ->selectRaw('COUNT(*) FILTER (WHERE d.decimal_latitude_visible AND d.decimal_longitude_visible AND e.decimal_latitude IS NOT NULL AND e.decimal_longitude IS NOT NULL) AS georreferenciados')
                ->selectRaw('COUNT(*) FILTER (WHERE d.type_status_visible AND e.type_status IS NOT NULL AND e.type_status <> \'\') AS ejemplares_tipo')
                ->selectRaw('MIN(e.fecha_colecta) FILTER (WHERE d.event_date_visible) AS primera_fecha')
                ->selectRaw('MAX(e.fecha_colecta) FILTER (WHERE d.event_date_visible) AS ultima_fecha')
                ->groupBy('t.nombre_cientifico')->get()->keyBy('especie')->all());
        }

        return view('catalogopublico::comparar-especies', compact('especies', 'a', 'b', 'datos'));
    }
}
