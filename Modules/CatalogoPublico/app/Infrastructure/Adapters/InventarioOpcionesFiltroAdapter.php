<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Infrastructure\Adapters;

use Illuminate\Support\Facades\DB;
use Modules\CatalogoPublico\Application\Ports\ProveedorOpcionesFiltroPort;

final class InventarioOpcionesFiltroAdapter implements ProveedorOpcionesFiltroPort
{
    public function obtenerPreparaciones(): array
    {
        return DB::table('taxonomia.especimenes')
            ->whereNotNull('preparations')
            ->where('preparations', '<>', '')
            ->distinct()
            ->orderBy('preparations')
            ->pluck('preparations')
            ->map(fn ($v) => (string) $v)
            ->values()
            ->all();
    }

    public function obtenerBiomas(): array
    {
        return DB::table('taxonomia.especimenes')
            ->whereNotNull('biome')
            ->where('biome', '<>', '')
            ->distinct()
            ->orderBy('biome')
            ->pluck('biome')
            ->map(fn ($v) => (string) $v)
            ->values()
            ->all();
    }

    public function obtenerMetodosRecoleccion(): array
    {
        return DB::table('taxonomia.muestras_colecta')
            ->whereNotNull('sampling_protocol')
            ->where('sampling_protocol', '<>', '')
            ->distinct()
            ->orderBy('sampling_protocol')
            ->pluck('sampling_protocol')
            ->map(fn ($v) => (string) $v)
            ->values()
            ->all();
    }

    public function obtenerColectores(): array
    {
        return DB::table('taxonomia.especimenes')
            ->whereNotNull('colector')
            ->where('colector', '<>', '')
            ->distinct()
            ->orderBy('colector')
            ->pluck('colector')
            ->map(fn ($v) => (string) $v)
            ->values()
            ->all();
    }
}
