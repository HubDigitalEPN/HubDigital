<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Repositories\EloquentProveedorEspecimenesParaArbol;

final class SugerenciasBusquedaPublica
{
    public function __invoke(Request $request, EloquentProveedorEspecimenesParaArbol $repositorio): JsonResponse
    {
        $datos = $request->validate([
            'campo' => ['required', 'in:filtroCatalogo,filtroTaxon'],
            'texto' => ['required', 'string', 'min:2', 'max:120'],
        ]);

        return response()->json(['sugerencias' => $repositorio->sugerenciasPublicas($datos['campo'], trim($datos['texto']))])
            ->header('Cache-Control', 'no-cache')->header('X-Portal-Public-Cache', 'data');
    }
}
