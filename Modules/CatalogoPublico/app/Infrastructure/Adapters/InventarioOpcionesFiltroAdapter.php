<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Infrastructure\Adapters;

use Modules\CatalogoPublico\Infrastructure\ElegibilidadGeograficaPortal;

use Illuminate\Support\Facades\DB;
use Modules\CatalogoPublico\Application\Ports\ProveedorOpcionesFiltroPort;
use Modules\CatalogoPublico\Infrastructure\ProtocoloColectaPublico;
use Modules\CatalogoPublico\Infrastructure\CalidadDatoPublico;
use Modules\CatalogoPublico\Infrastructure\NormalizacionGeografica;

final class InventarioOpcionesFiltroAdapter implements ProveedorOpcionesFiltroPort
{
    public function obtenerPreparaciones(): array
    {
        return DB::table('taxonomia.especimenes as e')
            ->join('divulgacion.especimenes_divulgables as d', 'd.especimen_id', '=', 'e.id')
            ->where('d.publicado', true)->whereRaw(ElegibilidadGeograficaPortal::sql('e', 'd'))
            ->whereNotNull('e.preparations')
            ->where('e.preparations', '<>', '')
            ->distinct()
            ->orderBy('e.preparations')
            ->pluck('e.preparations')
            ->map(fn ($v) => (string) $v)
            ->filter(static fn (string $valor): bool => NormalizacionGeografica::contieneNombre($valor))
            ->values()
            ->all();
    }

    public function obtenerBiomas(): array
    {
        return DB::table('taxonomia.especimenes as e')
            ->join('divulgacion.especimenes_divulgables as d', 'd.especimen_id', '=', 'e.id')
            ->where('d.publicado', true)->whereRaw(ElegibilidadGeograficaPortal::sql('e', 'd'))
            ->whereNotNull('e.biome')
            ->where('e.biome', '<>', '')
            ->distinct()
            ->orderBy('e.biome')
            ->pluck('e.biome')
            ->map(fn ($v) => (string) $v)
            ->filter(static fn (string $valor): bool => NormalizacionGeografica::contieneNombre($valor))
            ->values()
            ->all();
    }

    public function obtenerMetodosRecoleccion(): array
    {
        $protocolo = ProtocoloColectaPublico::claveSql('e', 'm');
        return DB::table('taxonomia.especimenes as e')
            ->leftJoin('taxonomia.muestras_colecta as m', 'm.id', '=', 'e.muestra_id')
            ->join('divulgacion.especimenes_divulgables as d', 'd.especimen_id', '=', 'e.id')
            ->where('d.publicado', true)->whereRaw(ElegibilidadGeograficaPortal::sql('e', 'd'))
            ->where('d.sampling_protocol_visible', true)
            ->whereRaw(CalidadDatoPublico::textoValido($protocolo))
            ->distinct()
            ->selectRaw($protocolo.' AS metodo')->orderBy('metodo')
            ->pluck('metodo')
            ->map(fn ($v) => (string) $v)
            ->filter(static fn (string $valor): bool => NormalizacionGeografica::contieneNombre($valor))
            ->values()
            ->all();
    }

    public function obtenerColectores(): array
    {
        return DB::table('taxonomia.especimenes as e')
            ->join('divulgacion.especimenes_divulgables as d', 'd.especimen_id', '=', 'e.id')
            ->where('d.publicado', true)->whereRaw(ElegibilidadGeograficaPortal::sql('e', 'd'))
            ->where('d.recorded_by_visible', true)
            ->whereNotNull('e.colector')
            ->where('e.colector', '<>', '')
            ->distinct()
            ->orderBy('e.colector')
            ->pluck('e.colector')
            ->map(fn ($v) => (string) $v)
            ->filter(static fn (string $valor): bool => NormalizacionGeografica::contieneNombre($valor))
            ->values()
            ->all();
    }
}
