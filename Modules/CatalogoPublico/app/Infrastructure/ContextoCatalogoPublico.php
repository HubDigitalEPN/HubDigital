<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Infrastructure;

use Illuminate\Http\Request;
use Modules\CatalogoPublico\Domain\ValueObjects\FiltrosBusqueda;
use Modules\CatalogoPublico\Presentation\Http\Controllers\PortalCatalogo;

final class ContextoCatalogoPublico
{
    /** El árbol HTTP comparte exactamente los filtros aplicados de tarjetas y mapa. */
    public static function filtros(Request $request, string $taxon = ''): FiltrosBusqueda
    {
        $request->validate(['contexto' => ['sometimes', 'array', 'max:60']]);
        $datos = []; $reglas = [];
        $defectos = (new \ReflectionClass(PortalCatalogo::class))->getDefaultProperties();
        foreach (PortalCatalogo::PROPIEDADES_URL as $alias => $propiedad) {
            if (!str_starts_with($propiedad, 'filtro')) continue;
            $array = is_array($defectos[$propiedad] ?? null);
            $reglas[$alias] = $array ? ['sometimes', 'nullable', 'array', 'max:100'] : ['sometimes', 'nullable', 'string', 'max:240'];
            if ($array) $reglas[$alias.'.*'] = ['string', 'max:240'];
        }
        $reglas['nivel'] = ['nullable', 'in:,phylum,class,order,family,genus,species'];
        $reglas['taxon'] = ['nullable', 'string', 'max:120'];
        // El taxón propio del explorador viaja con otro nombre para conservar la navegación.
        $estado = validator($request->query('contexto', []), $reglas)->validate();
        foreach (PortalCatalogo::PROPIEDADES_URL as $alias => $propiedad) if (isset($estado[$alias]) && str_starts_with($propiedad, 'filtro')) $datos[$propiedad] = $estado[$alias];
        if ($taxon !== '') { $datos['filtroTaxonId'] = $taxon; $datos['filtroTaxonIds'] = []; }
        $datos['nivelNavegado'] = $estado['nivel'] ?? '';
        $datos['taxonNavegado'] = $estado['taxon'] ?? '';
        return FiltrosBusqueda::desde($datos);
    }
}
