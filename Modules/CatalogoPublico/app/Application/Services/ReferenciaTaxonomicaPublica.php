<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\Services;

/** Contraste externo congelado de QA7; no adjudica identificaciones ni reemplaza la fuente. */
final class ReferenciaTaxonomicaPublica
{
    private ?array $referencias = null;

    /** Alias documentados por ID de evidencia; no se infieren a partir de sinónimos externos. */
    private const HISTORIAL = [
        'TX7-009' => ['ECtatoma ruidum', 'Ectatomma ruidum'],
        'TX7-072' => ['Dolichoderus dEColatus', 'Dolichoderus decollatus'],
    ];

    public function para(?string $nombre, bool $familiaVisible = false): ?array
    {
        if ($nombre === null || trim($nombre) === '') return null;
        if ($this->referencias === null) {
            $archivo = resource_path('data/qa7-referencias-taxonomicas.json');
            $this->referencias = is_file($archivo) ? (json_decode(file_get_contents($archivo), true, flags: JSON_THROW_ON_ERROR)['samples'] ?? []) : [];
        }
        $fila = array_find($this->referencias, static fn (array $r): bool => mb_strtolower($r['portal_name']) === mb_strtolower($nombre));
        if ($fila === null) {
            $id = array_find_key(self::HISTORIAL, static fn (array $alias): bool => array_any($alias, static fn (string $n): bool => mb_strtolower($n) === mb_strtolower($nombre)));
            if ($id !== null) $fila = array_find($this->referencias, static fn (array $r): bool => $r['sample_id'] === $id);
        }
        if ($fila === null) return null;
        $uso = $fila['reference_accepted_usage'] ?? $fila['reference_usage'] ?? [];
        $pendiente = ! in_array($fila['name_relation_category'], ['nombre_concordante', 'diferencia_de_mayusculas'], true)
            || mb_strtolower($nombre) === 'naesiotus eschariferus';
        return ['original' => $nombre, 'anterior' => $fila['portal_name'] !== $nombre ? $fila['portal_name'] : null,
            'historial' => self::HISTORIAL[$fila['sample_id']] ?? [$fila['portal_name']],
            'candidato' => $fila['name_relation_category'] === 'coincidencia_automatica_rechazada' ? null : ($uso['canonicalName'] ?? null),
            'rango_referencia' => $uso['rank'] ?? null, 'estado_referencia' => $uso['status'] ?? null,
            'familia_referencia' => $familiaVisible ? ($fila['reference_classification']['FAMILY'] ?? null) : null,
            'categoria' => $fila['name_relation_category'], 'fuente' => 'GBIF · Catalogue of Life Extended Release (COL XR)',
            'checklist' => '7ddf754f-d193-4cc9-b351-99906754a03b',
            'fecha' => substr($fila['effective_retrieved_at_utc'] ?? '2026-10-04', 0, 10),
            'url' => $fila['effective_query_url'], 'evidencia' => $fila['sample_id'],
            'decision' => $pendiente ? 'Pendiente de cotejo y decisión curatorial' : 'Contraste textual concordante; identificación física no verificada',
            'interpretacion' => $fila['interpretation'],
            'limite' => 'La consulta no devolvió una edición inmutable de COL XR. El contraste no reidentifica ejemplares ni valida todo el linaje del portal. Los calificadores, códigos locales y nombres originales se conservan.'];
    }
}
