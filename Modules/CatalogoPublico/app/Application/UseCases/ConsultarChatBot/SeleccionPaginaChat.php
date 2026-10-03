<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot;

use Illuminate\Support\Facades\Validator;
use Modules\CatalogoPublico\Domain\ValueObjects\FiltrosBusqueda;

/** Reconstruye exclusivamente los criterios públicos aplicados, sin usar un borrador. */
final readonly class SeleccionPaginaChat
{
    private function __construct(public array $parametros, public FiltrosBusqueda $filtros) {}

    public static function desde(array $entrada): ?self
    {
        $reglas = [];
        foreach (['fc', 'ft', 'fco', 'fh', 'fsti', 'fca', 'fes', 'fpais', 'fprov', 'taxon'] as $clave) {
            $reglas[$clave] = ['nullable', 'string', 'max:2000'];
        }
        foreach (['fp', 'fg', 'fm', 'fb'] as $clave) {
            $reglas[$clave] = ['sometimes', 'array', 'max:100'];
            $reglas[$clave.'.*'] = ['required', 'string', 'max:500'];
        }
        $reglas += [
            'nivel' => ['nullable', 'required_with:taxon', 'string', 'in:phylum,class,order,family,genus,species'],
            'ffd' => ['nullable', 'date_format:Y-m-d'],
            'ffh' => array_filter(['nullable', 'date_format:Y-m-d', ! empty($entrada['ffd']) ? 'after_or_equal:ffd' : null]),
            'flat' => ['nullable', 'required_with:flax', 'numeric', 'between:-90,90'],
            'flax' => ['nullable', 'required_with:flat', 'numeric', 'between:-90,90', 'gte:flat'],
            'flon' => ['nullable', 'required_with:flox', 'numeric', 'between:-180,180'],
            'flox' => ['nullable', 'required_with:flon', 'numeric', 'between:-180,180', 'gte:flon'],
            'fed' => ['nullable', 'numeric'],
            'feh' => array_filter(['nullable', 'numeric', isset($entrada['fed']) && $entrada['fed'] !== '' ? 'gte:fed' : null]),
            'fph' => ['nullable', 'uuid'], 'fmes' => ['nullable', 'integer', 'between:1,12'],
            'fid' => ['nullable', 'in:especie,superior'], 'fgeo' => ['nullable', 'in:0,1'], 'fap' => ['nullable', 'in:0,1'],
        ];
        $reglas['taxon'][] = 'required_with:nivel';
        if (Validator::make(array_intersect_key($entrada, $reglas), $reglas)->stopOnFirstFailure()->fails()) return null;

        $p = EnlaceSeleccionCatalogo::limpiar($entrada);
        $filtros = FiltrosBusqueda::desde([
            'filtroCatalogo' => $p['fc'] ?? '', 'filtroPreparaciones' => $p['fp'] ?? [],
            'filtroTaxon' => $p['ft'] ?? '', 'filtroGeografias' => $p['fg'] ?? [], 'filtroColector' => $p['fco'] ?? '',
            'filtroFechaDesde' => $p['ffd'] ?? '', 'filtroFechaHasta' => $p['ffh'] ?? '', 'filtroMetodos' => $p['fm'] ?? [],
            'filtroLatMin' => $p['flat'] ?? '', 'filtroLatMax' => $p['flax'] ?? '',
            'filtroLonMin' => $p['flon'] ?? '', 'filtroLonMax' => $p['flox'] ?? '',
            'filtroElevDesde' => $p['fed'] ?? '', 'filtroElevHasta' => $p['feh'] ?? '',
            'filtroBiomas' => $p['fb'] ?? [], 'filtroHabitat' => $p['fh'] ?? '', 'filtroTipo' => $p['fsti'] ?? '',
            'filtroCasta' => $p['fca'] ?? '', 'filtroEstadio' => $p['fes'] ?? '',
            'filtroProvincia' => $p['fprov'] ?? '', 'filtroPais' => $p['fpais'] ?? '', 'filtroFiloId' => $p['fph'] ?? '',
            'filtroMes' => $p['fmes'] ?? '', 'filtroIdentificacion' => $p['fid'] ?? '',
            'filtroSoloUbicacion' => $p['fgeo'] ?? '', 'filtroDatosCompletos' => $p['fap'] ?? '',
        ]);

        return new self($p, $filtros);
    }
}
