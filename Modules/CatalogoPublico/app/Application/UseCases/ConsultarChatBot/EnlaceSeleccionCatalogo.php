<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot;

use Modules\CatalogoPublico\Domain\ValueObjects\FiltrosBusqueda;

/** Parámetros públicos del enlace: nunca incluye ejemplares ni el borrador del formulario. */
final class EnlaceSeleccionCatalogo
{
    private const ESCALARES = ['nivel', 'taxon', 'fc', 'ft', 'fco', 'ffd', 'ffh', 'flat', 'flax', 'flon', 'flox',
        'fed', 'feh', 'fh', 'fsti', 'fca', 'fes', 'fpais', 'fprov', 'fph', 'fmes', 'fid', 'fgeo', 'fap'];

    private const LISTAS = ['fp', 'fg', 'fm', 'fb'];

    public static function parametros(FiltrosBusqueda $filtros, string $nivel, string $taxon): array
    {
        return self::limpiar([
            'nivel' => $nivel, 'taxon' => $taxon,
            'fc' => implode(', ', $filtros->codigosCatalogo), 'fp' => $filtros->preparaciones,
            'ft' => $filtros->taxonNombre, 'fg' => $filtros->geografias,
            'fco' => implode(', ', $filtros->colectores), 'ffd' => $filtros->fechaDesde?->format('Y-m-d'),
            'ffh' => $filtros->fechaHasta?->format('Y-m-d'), 'fm' => $filtros->metodosRecoleccion,
            'flat' => $filtros->latMin, 'flax' => $filtros->latMax, 'flon' => $filtros->lonMin, 'flox' => $filtros->lonMax,
            'fed' => $filtros->elevDesde, 'feh' => $filtros->elevHasta, 'fb' => $filtros->biomas,
            'fh' => $filtros->habitat, 'fsti' => $filtros->tipo, 'fca' => $filtros->casta, 'fes' => $filtros->estadio,
            'fpais' => $filtros->pais, 'fprov' => $filtros->provincia, 'fph' => $filtros->filoId,
            'fmes' => $filtros->mes, 'fid' => $filtros->identificacion,
            'fgeo' => $filtros->soloUbicacion ? '1' : '', 'fap' => $filtros->datosCompletos ? '1' : '',
        ]);
    }

    public static function limpiar(array $parametros): array
    {
        $publicos = [];
        foreach (self::ESCALARES as $clave) {
            $valor = $parametros[$clave] ?? null;
            if (is_scalar($valor) && (string) $valor !== '') $publicos[$clave] = (string) $valor;
        }
        foreach (self::LISTAS as $clave) {
            if (! is_array($parametros[$clave] ?? null)) continue;
            $valores = array_values(array_filter(array_map(
                static fn (mixed $valor): string => is_scalar($valor) ? (string) $valor : '',
                $parametros[$clave],
            ), static fn (string $valor): bool => $valor !== ''));
            if ($valores !== []) $publicos[$clave] = $valores;
        }
        if (! in_array($publicos['nivel'] ?? '', ['phylum', 'class', 'order', 'family', 'genus', 'species'], true)
            || ! isset($publicos['taxon'])) {
            unset($publicos['nivel'], $publicos['taxon']);
        }

        return $publicos;
    }
}
