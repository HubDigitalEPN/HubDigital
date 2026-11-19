<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\FotosEditoriales;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class FotoEditorialPortal
{
    public function __invoke(Request $request, string $foto, string $hash): Response
    {
        $response = response(FotosEditoriales::contenido($foto, $hash))->header('Content-Type', 'image/webp')
            ->header('X-Content-Type-Options', 'nosniff')->header('Cache-Control', 'public, max-age=3600')->setEtag($hash);
        $response->isNotModified($request);
        return $response;
    }
}
