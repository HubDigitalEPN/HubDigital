<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\PaginaLaboratorio;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class FotoPaginaLaboratorio
{
    public function __invoke(Request $request, string $foto, string $hash, PaginaLaboratorio $pagina): Response
    {
        $respuesta = response($pagina->contenidoFoto($foto, $hash))->header('Content-Type', 'image/webp')
            ->header('X-Content-Type-Options', 'nosniff')->header('Cache-Control', 'public, max-age=3600')->setEtag($hash);
        $respuesta->isNotModified($request);
        return $respuesta;
    }
}
