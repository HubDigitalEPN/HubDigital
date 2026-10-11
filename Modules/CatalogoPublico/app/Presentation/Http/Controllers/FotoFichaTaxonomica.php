<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Presentation\Http\Controllers;

use Illuminate\Http\Request;
use Modules\CatalogoPublico\Domain\ValueObjects\FiltrosBusqueda;
use Modules\CatalogoPublico\Infrastructure\ExploradorTaxonomicoPublico;
use Modules\CatalogoPublico\Infrastructure\FichasTaxonomicas;
use Symfony\Component\HttpFoundation\Response;

final class FotoFichaTaxonomica
{
    public function __invoke(Request $request, string $taxon, ExploradorTaxonomicoPublico $explorador, FichasTaxonomicas $fichas): Response
    {
        $privada = $request->routeIs('admin.ficha-foto');
        if ($privada) abort_unless(auth()->user()?->esCurador(), 403);
        else abort_unless($explorador->taxon(FiltrosBusqueda::vacio(), $taxon), 404);
        $ficha = $fichas->buscar($taxon, !$privada);
        abort_unless($ficha?->foto_ruta, 404);
        try { $contenido = $fichas->contenidoFoto($ficha); }
        catch (\Throwable $error) { report($error); abort(404); }
        return response($contenido, 200, ['Content-Type' => 'image/webp', 'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff']);
    }
}
