<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\ClaseProblemaRevision;

/**
 * Traduce el texto de `motivo_revision` a las clases de problema que contiene.
 *
 * El importador acumula avisos en una sola cadena separados por "; ", de modo
 * que una fila puede arrastrar a la vez una fecha ilegible y unas coordenadas
 * fuera de rango. Por eso devuelve una LISTA y no una sola clase: quedarse con
 * la primera coincidencia escondería el resto de problemas de esa fila.
 *
 * Servicio puro: sin estado, sin dependencias de Laravel.
 *
 * Las subcadenas de detección son deliberadamente largas ('coordenadas no
 * numéricas' y no 'coordenada'). Los motivos de duplicados los redacta el
 * curador a mano, así que una subcadena corta como 'fecha' clasificaría mal
 * cualquier nota suya que mencione de pasada una fecha.
 */
final class ClasificadorMotivoRevision
{
    /** Separador con el que el importador acumula varios avisos. */
    public const SEPARADOR = '; ';

    /**
     * Subcadena → clase. El orden importa solo para la salida: las clases se
     * devuelven en este orden, de más a menos accionable.
     *
     * @var array<string, string>
     */
    private const PATRONES = [
        'coordenadas no numéricas' => 'coordenadas',
        'fecha_colecta no parseable' => 'fecha',
        'taxonomía sin validar' => 'taxonomia',
        'occurrence_id ausente' => 'occurrence_id',
        'individual_count no numérico' => 'individual_count',
    ];

    /**
     * Clases detectadas en el motivo, sin repetir y en orden de accionabilidad.
     *
     * Un motivo con texto pero sin ninguna coincidencia conocida (lo escribió
     * un curador a mano) se clasifica como genérico: sigue siendo una
     * incidencia real que merece señalarse, aunque no sepamos encaminarla.
     *
     * @return list<ClaseProblemaRevision>
     */
    public function clasificar(?string $motivo): array
    {
        $motivo = $motivo !== null ? trim($motivo) : '';
        if ($motivo === '') {
            return [];
        }

        // mb_strtolower: dos patrones llevan tilde ('taxonomía') y strtolower()
        // de PHP trabaja byte a byte, así que rompería la comparación en UTF-8.
        $normalizado = mb_strtolower($motivo, 'UTF-8');

        $clases = [];
        foreach (self::PATRONES as $patron => $clase) {
            if (str_contains($normalizado, $patron)) {
                $clases[] = ClaseProblemaRevision::from($clase);
            }
        }

        return $clases !== [] ? $clases : [ClaseProblemaRevision::Generico];
    }

    /**
     * Trocea el motivo en sus avisos individuales, para mostrarlos en línea
     * aparte en vez de como un párrafo pegado.
     *
     * @return list<string>
     */
    public function desglosar(?string $motivo): array
    {
        $motivo = $motivo !== null ? trim($motivo) : '';
        if ($motivo === '') {
            return [];
        }

        return array_values(array_filter(
            array_map(trim(...), explode(self::SEPARADOR, $motivo)),
            fn (string $parte) => $parte !== '',
        ));
    }
}
