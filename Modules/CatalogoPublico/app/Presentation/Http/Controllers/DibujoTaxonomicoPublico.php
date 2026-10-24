<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Modules\CatalogoPublico\Domain\ValueObjects\FiltrosBusqueda;
use Modules\CatalogoPublico\Infrastructure\ExploradorTaxonomicoPublico;

final class DibujoTaxonomicoPublico
{
    public function __invoke(Request $request, ExploradorTaxonomicoPublico $explorador): JsonResponse
    {
        $datos = $request->validate([
            'taxon' => ['nullable', 'uuid'], 'rama' => ['nullable', 'string', 'max:100'],
            'vista' => ['required', 'in:auto,rectangular,semicircular,circular'],
            'escala' => ['required', 'numeric', 'between:40,300'],
            'expandidos' => ['sometimes', 'array', 'max:5000'], 'expandidos.*' => ['string', 'max:100'],
        ]);
        $id = $datos['taxon'] ?? '';
        abort_if($id !== '' && $explorador->taxon(FiltrosBusqueda::vacio(), $id) === null, 404);
        $filtros = \Modules\CatalogoPublico\Infrastructure\ContextoCatalogoPublico::filtros($request, $id);
        $estructura = $explorador->estructura($filtros);
        $claves = array_column($estructura['nodos'], 'clave');
        $rama = $datos['rama'] ?? '';
        abort_if($rama !== '' && !in_array($rama, $claves, true), 404);
        $expandidos = array_values(array_unique(array_intersect($datos['expandidos'] ?? [], $claves)));
        sort($expandidos);
        $revision = (string) DB::table('divulgacion.portal_cache_revision')->where('id', 1)->value('version')
            .'-'.substr(hash_file('sha256', base_path('resources/python/portal_ete/server.py')), 0, 12);
        $clave = 'portal-ete:'.hash('sha256', json_encode([$revision, $filtros, $id, $rama, $datos['vista'], (float) $datos['escala'], $expandidos]));
        try {
            $dibujo = Cache::remember($clave, 600, function () use ($estructura, $datos, $rama, $expandidos) {
                return Http::connectTimeout(2)->timeout(config('portal-ete.timeout'))
                    ->post(rtrim(config('portal-ete.url'), '/').'/render', [
                        'nodos' => $estructura['nodos'], 'rama' => $rama, 'vista' => $datos['vista'],
                        'escala' => (float) $datos['escala'], 'expandidos' => $expandidos,
                    ])->throw()->json();
            });
        } catch (\Illuminate\Http\Client\ConnectionException|\Illuminate\Http\Client\RequestException $error) {
            report($error);
            return response()->json(['error' => 'El visor ETE no está disponible. Vuelve a intentarlo.'], 503);
        }
        return response()->json($dibujo, 200, [
            'Cache-Control' => 'no-cache', 'X-Portal-Public-Cache' => 'data', 'X-Portal-Revision' => $revision,
        ]);
    }
}
