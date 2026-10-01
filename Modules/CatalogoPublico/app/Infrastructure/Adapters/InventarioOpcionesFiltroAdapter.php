<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Infrastructure\Adapters;

use Illuminate\Support\Facades\DB;
use Modules\CatalogoPublico\Application\Ports\ProveedorOpcionesFiltroPort;

final class InventarioOpcionesFiltroAdapter implements ProveedorOpcionesFiltroPort
{
    public function obtenerPreparaciones(): array
    {
        return DB::table('taxonomia.especimenes as e')
            ->join('divulgacion.especimenes_divulgables as d', 'd.especimen_id', '=', 'e.id')
            ->where('d.publicado', true)->where('e.coordenadas_otras_regiones', false)
            ->whereNotNull('e.preparations')
            ->where('e.preparations', '<>', '')
            ->distinct()
            ->orderBy('e.preparations')
            ->pluck('e.preparations')
            ->map(fn ($v) => (string) $v)
            ->values()
            ->all();
    }

    public function obtenerBiomas(): array
    {
        return DB::table('taxonomia.especimenes as e')
            ->join('divulgacion.especimenes_divulgables as d', 'd.especimen_id', '=', 'e.id')
            ->where('d.publicado', true)->where('e.coordenadas_otras_regiones', false)
            ->whereNotNull('e.biome')
            ->where('e.biome', '<>', '')
            ->distinct()
            ->orderBy('e.biome')
            ->pluck('e.biome')
            ->map(fn ($v) => (string) $v)
            ->values()
            ->all();
    }

    public function obtenerMetodosRecoleccion(): array
    {
        return DB::table('taxonomia.muestras_colecta as m')
            ->join('taxonomia.especimenes as e', 'e.muestra_id', '=', 'm.id')
            ->join('divulgacion.especimenes_divulgables as d', 'd.especimen_id', '=', 'e.id')
            ->where('d.publicado', true)->where('e.coordenadas_otras_regiones', false)
            ->where('d.sampling_protocol_visible', true)
            ->whereNotNull('m.sampling_protocol')
            ->where('m.sampling_protocol', '<>', '')
            ->distinct()
            ->orderBy('m.sampling_protocol')
            ->pluck('m.sampling_protocol')
            ->map(fn ($v) => (string) $v)
            ->values()
            ->all();
    }

    public function obtenerColectores(): array
    {
        return DB::table('taxonomia.especimenes as e')
            ->join('divulgacion.especimenes_divulgables as d', 'd.especimen_id', '=', 'e.id')
            ->where('d.publicado', true)->where('e.coordenadas_otras_regiones', false)
            ->where('d.recorded_by_visible', true)
            ->whereNotNull('e.colector')
            ->where('e.colector', '<>', '')
            ->distinct()
            ->orderBy('e.colector')
            ->pluck('e.colector')
            ->map(fn ($v) => (string) $v)
            ->values()
            ->all();
    }
}
