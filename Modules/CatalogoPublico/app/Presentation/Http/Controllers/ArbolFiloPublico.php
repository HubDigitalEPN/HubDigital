<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\CatalogoPublico\Domain\ValueObjects\FiltrosBusqueda;
use Modules\CatalogoPublico\Infrastructure\ExploradorTaxonomicoPublico;

final class ArbolFiloPublico
{
    public function __invoke(Request $request, ExploradorTaxonomicoPublico $explorador): JsonResponse
    {
        $datos = $request->validate(['padre' => ['nullable', 'string', 'max:100'], 'busquedaTaxon' => ['nullable', 'string', 'max:120']]);
        $filtros = FiltrosBusqueda::desde(['filtroTaxon' => $datos['busquedaTaxon'] ?? '']);
        return response()->json($explorador->consultar($filtros, $datos['padre'] ?? null), 200, [
            'Cache-Control' => 'no-cache', 'X-Portal-Public-Cache' => 'data',
            'X-Portal-Revision' => (string) DB::table('divulgacion.portal_cache_revision')->where('id', 1)->value('version'),
        ]);
    }
}
