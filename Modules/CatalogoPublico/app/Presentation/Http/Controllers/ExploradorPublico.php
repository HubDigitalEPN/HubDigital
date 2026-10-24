<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\CatalogoPublico\Domain\ValueObjects\FiltrosBusqueda;
use Modules\CatalogoPublico\Infrastructure\ExploradorTaxonomicoPublico;

final class ExploradorPublico
{
    public function __invoke(Request $request, ExploradorTaxonomicoPublico $explorador): JsonResponse
    {
        $datos = $request->validate([
            'padre' => ['nullable', 'string', 'max:100'], 'busqueda' => ['nullable', 'string', 'max:120'],
            'taxon' => ['nullable', 'uuid'], 'conservar' => ['sometimes', 'array', 'max:5000'],
            'conservar.*' => ['string', 'max:100'],
            'completo' => ['sometimes', 'boolean'],
        ]);
        $id = $datos['taxon'] ?? '';
        if ($id !== '' && $explorador->taxon(FiltrosBusqueda::vacio(), $id) === null) abort(404);
        $filtros = \Modules\CatalogoPublico\Infrastructure\ContextoCatalogoPublico::filtros($request, $id);
        $conservar = $datos['conservar'] ?? [];
        $abrirSeleccion = $id !== '' && empty($datos['padre']) && empty($datos['busqueda']) && $conservar === [];
        if ($abrirSeleccion) {
            $seleccion = $explorador->taxon($filtros, $id);
            abort_if($seleccion === null, 404);
            $conservar[] = $seleccion['clave'];
        }
        $completo = !empty($datos['completo']) && empty($datos['padre']) && empty($datos['busqueda']);
        $resultado = $completo ? $explorador->estructura($filtros)
            : $explorador->consultar($filtros, $datos['padre'] ?? null, $datos['busqueda'] ?? '', $conservar);
        if ($abrirSeleccion) {
            $porClave = array_column($resultado['nodos'], null, 'clave');
            $padre = $seleccion['padre']; $resultado['expandidos'] = [];
            while ($padre !== null && isset($porClave[$padre])) {
                $resultado['expandidos'][] = $padre;
                $padre = $porClave[$padre]['padre'];
            }
        }
        if ($completo && $id !== '') {
            $porClave = array_column($resultado['nodos'], null, 'clave');
            $seleccion = $explorador->taxon($filtros, $id);
            $padre = $seleccion['clave'];
            while ($padre !== null && isset($porClave[$padre])) {
                $resultado['expandidos'][] = $padre;
                $padre = $porClave[$padre]['padre'];
            }
        }
        $resultado['total'] = $resultado['totalCatalogo'];
        $resultado['expandidos'] = array_values(array_unique($resultado['expandidos']));
        $resultado['seleccionado'] = $id;
        return response()->json($resultado, 200, [
            'Cache-Control' => 'no-cache', 'X-Portal-Public-Cache' => 'data',
            'X-Portal-Revision' => (string) DB::table('divulgacion.portal_cache_revision')->where('id', 1)->value('version')
                .'-'.substr(hash_file('sha256', base_path('resources/python/portal_ete/server.py')), 0, 12),
        ]);
    }
}
