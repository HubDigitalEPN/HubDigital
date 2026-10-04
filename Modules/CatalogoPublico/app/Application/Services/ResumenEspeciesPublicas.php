<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\Services;

use Modules\CatalogoPublico\Infrastructure\CalidadDatoPublico;

/** Reutiliza los conteos públicos por taxón sin volver a consultar los ejemplares. */
final class ResumenEspeciesPublicas
{
    /**
     * La consulta de origen exige scientific_name_visible = true y conserva todos
     * los filtros de la selección; sus filas pueden separar permisos de familia/género.
     *
     * @param iterable<object> $conteosVisibles
     * @param array<string, object>|\ArrayAccess<string, object> $taxones
     * @param list<string> $idsValidos
     * @return array{especies: list<array{nombre: string, total: int}>, raras: list<array{nombre: string, total: int}>}
     */
    public static function desde(iterable $conteosVisibles, array|\ArrayAccess $taxones, array $idsValidos): array
    {
        $validos = array_fill_keys($idsValidos, true);
        $totales = [];
        foreach ($conteosVisibles as $fila) {
            $taxon = $taxones[$fila->taxon_id] ?? null;
            if ($taxon === null || $taxon->rango !== 'especie' || ! isset($validos[$fila->taxon_id])
                || ! CalidadDatoPublico::esTextoValido($taxon->nombre_cientifico)) continue;
            // El contrato SQL anterior agrupaba por nombre: distintos UUID y
            // variantes de permisos no crean etiquetas científicas adicionales.
            $nombre = $taxon->nombre_cientifico;
            $totales[$nombre] = ($totales[$nombre] ?? 0) + (int) $fila->total;
        }
        $filas = [];
        foreach ($totales as $nombre => $total) $filas[] = ['nombre' => (string) $nombre, 'total' => $total];
        usort($filas, static fn (array $a, array $b): int => $b['total'] <=> $a['total'] ?: strcmp($a['nombre'], $b['nombre']));
        $especies = array_slice($filas, 0, 20);
        // El límite de rareza se aplica al total del nombre, después de sumar UUIDs.
        $raras = array_values(array_filter($filas, static fn (array $fila): bool => $fila['total'] <= 3));
        usort($raras, static fn (array $a, array $b): int => $a['total'] <=> $b['total'] ?: strcmp($a['nombre'], $b['nombre']));
        return ['especies' => $especies, 'raras' => array_slice($raras, 0, 12)];
    }
}
