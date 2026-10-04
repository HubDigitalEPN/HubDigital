<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\Services;

use Modules\CatalogoPublico\Infrastructure\CalidadDatoPublico;
use Modules\CatalogoPublico\Infrastructure\NormalizacionGeografica;

/** Deriva diversidad de conteos públicos agrupados, sin hidratar ejemplares. */
final class ResumenDistribucionPublica
{
    /**
     * La consulta de origen exige nombre científico y provincia públicos; agrupa
     * por UUID y clave geográfica, manteniendo el conteo y una grafía de origen.
     *
     * @param iterable<object> $conteosPublicos
     * @param array<string, object>|\ArrayAccess<string, object> $taxones
     * @param list<string> $idsValidos
     * @return list<array{provincia: string, especies: int, registros: int}>
     */
    public static function riqueza(iterable $conteosPublicos, array|\ArrayAccess $taxones, array $idsValidos): array
    {
        $nombres = self::nombresEspecies($taxones, $idsValidos);
        $grupos = [];
        foreach ($conteosPublicos as $fila) {
            $nombre = $nombres[$fila->taxon_id] ?? null;
            $provincia = $fila->provincia ?? null;
            if ($nombre === null || ! CalidadDatoPublico::esTextoValido($provincia)
                || ! NormalizacionGeografica::contieneNombre($provincia)) continue;
            $clave = NormalizacionGeografica::normalizar($fila->provincia_clave ?? $provincia);
            $grupos[$clave] ??= ['provincia' => $provincia, 'nombres' => [], 'registros' => 0];
            // Representante de los conteos válidos; nunca de un UUID descartado.
            if (strcmp($provincia, $grupos[$clave]['provincia']) < 0) $grupos[$clave]['provincia'] = $provincia;
            $grupos[$clave]['nombres'][$nombre] = true;
            $grupos[$clave]['registros'] += (int) $fila->registros;
        }
        $filas = [];
        foreach ($grupos as $grupo) {
            $filas[] = ['provincia' => NormalizacionGeografica::nombresDisponibles([$grupo['provincia']])[0],
                'especies' => count($grupo['nombres']), 'registros' => $grupo['registros']];
        }
        usort($filas, static fn (array $a, array $b): int => $b['especies'] <=> $a['especies'] ?: strcmp($a['provincia'], $b['provincia']));
        return array_slice($filas, 0, 10);
    }

    /**
     * La consulta de origen exige nombre científico y fecha públicos y fecha
     * válida; agrupa únicamente por UUID y década, sin exponer fechas individuales.
     *
     * @param iterable<object> $conteosPublicos
     * @param array<string, object>|\ArrayAccess<string, object> $taxones
     * @param list<string> $idsValidos
     * @return list<array{decada: int, especies: int, registros: int}>
     */
    public static function decadas(iterable $conteosPublicos, array|\ArrayAccess $taxones, array $idsValidos): array
    {
        $nombres = self::nombresEspecies($taxones, $idsValidos);
        $grupos = [];
        foreach ($conteosPublicos as $fila) {
            $nombre = $nombres[$fila->taxon_id] ?? null;
            if ($nombre === null) continue;
            $decada = (int) $fila->decada;
            $grupos[$decada] ??= ['nombres' => [], 'registros' => 0];
            $grupos[$decada]['nombres'][$nombre] = true;
            $grupos[$decada]['registros'] += (int) $fila->registros;
        }
        ksort($grupos, SORT_NUMERIC);
        $filas = [];
        foreach ($grupos as $decada => $grupo) {
            $filas[] = ['decada' => (int) $decada, 'especies' => count($grupo['nombres']), 'registros' => $grupo['registros']];
        }
        return $filas;
    }

    /** @return array<string, string> */
    private static function nombresEspecies(array|\ArrayAccess $taxones, array $idsValidos): array
    {
        $nombres = [];
        foreach ($idsValidos as $id) {
            $taxon = $taxones[$id] ?? null;
            if ($taxon !== null && $taxon->rango === 'especie' && CalidadDatoPublico::esTextoValido($taxon->nombre_cientifico)) {
                $nombres[$id] = $taxon->nombre_cientifico;
            }
        }
        return $nombres;
    }
}
