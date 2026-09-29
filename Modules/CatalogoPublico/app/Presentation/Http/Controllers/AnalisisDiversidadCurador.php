<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Presentation\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\CatalogoPublico\Application\Services\CalcularDiversidadColeccion;

final class AnalisisDiversidadCurador
{
    public function __invoke(Request $request, CalcularDiversidadColeccion $calculador): View
    {
        $localidades = Cache::remember('curador:localidades-analisis:v1', 300, static fn () => DB::table('taxonomia.especimenes as e')
            ->join('taxonomia.localidades as l', 'l.id', '=', 'e.localidad_id')
            ->whereNotNull('e.localidad_id')
            ->select('l.id', 'l.nombre_canonico', 'l.state_province')
            ->distinct()->orderBy('l.state_province')->orderBy('l.nombre_canonico')->get());
        $provincias = Cache::remember('curador:provincias-analisis:v1', 300, static fn () => DB::table('taxonomia.especimenes')
            ->whereNotNull('state_province')->where('state_province', '<>', '')
            ->distinct()->orderBy('state_province')->pluck('state_province')->all());

        $filtros = $request->validate([
            'metricas' => ['sometimes', 'array', 'max:12'],
            'metricas.*' => ['string', Rule::in(array_keys(CalcularDiversidadColeccion::METRICAS))],
            'provincia' => ['nullable', 'string', Rule::in($provincias)],
            'desde' => ['nullable', 'integer', 'between:1800,2100'],
            'hasta' => ['nullable', 'integer', 'between:1800,2100'],
            'sitio_a' => ['nullable', 'uuid'],
            'sitio_b' => ['nullable', 'uuid'],
        ]);
        if (isset($filtros['desde'], $filtros['hasta']) && (int) $filtros['desde'] > (int) $filtros['hasta']) {
            throw ValidationException::withMessages(['hasta' => 'El año final debe ser igual o posterior al inicial.']);
        }
        $sitiosValidos = array_fill_keys($localidades->pluck('id')->all(), true);
        foreach (['sitio_a', 'sitio_b'] as $sitio) {
            if (isset($filtros[$sitio]) && ! isset($sitiosValidos[$filtros[$sitio]])) {
                abort(422, 'La localidad seleccionada no pertenece a la colección.');
            }
        }

        $metricas = array_values(array_unique($filtros['metricas'] ?? []));
        $resultado = $metricas === [] ? null : Cache::remember(
            'curador:diversidad:v1:'.sha1(json_encode([$metricas, $filtros])),
            300,
            fn () => $calculador->ejecutar($metricas, $filtros),
        );

        return view('catalogopublico::analisis-diversidad-curador', compact('localidades', 'provincias', 'filtros', 'metricas', 'resultado'));
    }
}
