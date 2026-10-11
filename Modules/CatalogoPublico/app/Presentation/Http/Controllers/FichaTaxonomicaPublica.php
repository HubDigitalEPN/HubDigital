<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\CatalogoPublico\Domain\ValueObjects\FiltrosBusqueda;
use Modules\CatalogoPublico\Infrastructure\ExploradorTaxonomicoPublico;
use Modules\CatalogoPublico\Infrastructure\FichasTaxonomicas;

final class FichaTaxonomicaPublica
{
    public function __invoke(string $taxon, ExploradorTaxonomicoPublico $explorador, FichasTaxonomicas $fichas): JsonResponse
    {
        abort_unless($explorador->taxon(FiltrosBusqueda::vacio(), $taxon), 404);
        $ficha = $fichas->buscar($taxon, true);
        return response()->json([
            'id' => $taxon, 'descripcion' => $ficha->descripcion ?? '',
            'foto' => ($ficha?->foto_ruta) ? route('portal.ficha-foto', ['taxon' => $taxon, 'v' => $ficha->foto_sha256]) : null,
            'alt' => $ficha->foto_alt ?? '', 'credito' => $ficha->credito ?? '', 'fuente' => $ficha->fuente_url ?? '',
        ], 200, ['Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff']);
    }
}
