<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** Lista blanca de consultas determinísticas sobre ejemplares divulgables. */
final class ConsultaCatalogoPublico
{
    public function __construct(private readonly DetectorEntidadesChat $detector, private readonly TextoChat $texto) {}

    public function responder(string $pregunta, array $contexto = []): ?array
    {
        if (! config('chatbot.specimen_search', true)) {
            return null;
        }
        $normal = $this->texto->normalizar($pregunta);
        $correction = (bool) preg_match('/^(perdon|corrijo|quise decir|queria decir|no )\b/', $normal);
        $followup = (bool) preg_match('/^(?:y\s+de\s+|y\s+)?cuantos?\b|^y\s+de\s+|^donde\s+los\s+encontraron\b/', $normal);
        $queryText = $pregunta;
        if ($correction && str_starts_with($normal, 'no ')) {
            // Conserva mayúsculas del nombre o localidad: el detector las usa para acotar entidades.
            $clauses = preg_split('/\s*(?:[,;]|\bpero\b|\bsino\b)\s*/iu', $pregunta) ?: [];
            if (count($clauses) > 1) $queryText = (string) end($clauses);
        }
        $entities = $this->detector->extraer($queryText);
        if (($correction || $followup) && $contexto !== []) {
            $previous = array_intersect_key($contexto, array_flip(['taxon', 'provincia', 'localidad', 'pais']));
            if (isset($entities['provincia']) || isset($entities['localidad']) || isset($entities['pais'])) {
                unset($previous['provincia'], $previous['localidad'], $previous['pais']);
            }
            $entities = array_replace($previous, $entities);
        }
        $options = [['label' => 'Abrir catálogo para ver más', 'url' => route('portal.catalogo')]];
        if (isset($entities['codigo'])) {
            return $this->porCodigo($entities['codigo'], $options);
        }
        if ($entities === [] && preg_match('/(?:que|cuales)\s+familias\s+(?:tienen|hay|existen)/', $normal)) {
            return $this->familias($options);
        }
        if (isset($entities['taxon']) && preg_match('/\bgeneros\b/', $normal)) {
            return $this->generos($entities['taxon'], $options);
        }
        if ($entities === [] && ! preg_match('/\bcuant[oa]s?\s+(?:registros|especimenes|ejemplares|especies)\s+(?:tienen|hay|estan)/', $normal)) {
            return null;
        }
        $query = $this->publicos();
        if (isset($entities['taxon'])) {
            $query->where('d.scientific_name_visible', true);
            $query->whereRaw('e.taxon_id IN (
                WITH RECURSIVE taxa AS (
                    SELECT id FROM taxonomia.taxones WHERE lower(nombre_cientifico) = lower(?)
                    UNION
                    SELECT t.id FROM taxonomia.taxones t JOIN taxa a ON t.padre_id = a.id
                ) SELECT id FROM taxa)', [$entities['taxon']]);
        }
        if (isset($entities['provincia'])) {
            $query->where('d.state_province_visible', true)
                ->whereRaw('lower(e.state_province) = lower(?)', [$entities['provincia']]);
        }
        if (isset($entities['localidad'])) {
            $query->where('d.locality_name_visible', true)
                ->whereRaw('lower(e.locality_name) = lower(?)', [$entities['localidad']]);
        }
        if (isset($entities['pais'])) {
            $query->where('d.country_visible', true)
                ->whereRaw('lower(e.country) = lower(?)', [$entities['pais']]);
        }
        if (preg_match('/^donde\s+los\s+encontraron\b/', $normal) && $entities !== []) {
            return $this->localidades($query, $entities, $options);
        }
        $total = (clone $query)->count('e.id');
        $label = implode(' en ', array_values(array_filter([$entities['taxon'] ?? null, $entities['provincia'] ?? $entities['localidad'] ?? $entities['pais'] ?? null])));
        $de = $label === '' ? '' : ' de '.$label;
        if ($total === 0) {
            $suggestions = isset($entities['taxon']) ? $this->sugerirTaxonPublico($entities['taxon']) : [];
            foreach ($suggestions as $name) $options[] = ['label' => 'Buscar '.$name, 'pregunta' => '¿Tienen '.$name.'?'];
            $hint = $suggestions === [] ? '' : ' ¿Te refieres a '.implode(' o ', $suggestions).'? Confirma el nombre antes de buscar.';
            return $this->resultado('No encontré registros publicados'.$de.' en el catálogo. Esto no confirma si existen ejemplares no divulgados.'.$hint, 'catalogo.count', $entities, 0, [], $options);
        }
        if (preg_match('/\bespecies\b/', $normal)) {
            $query->where('t.rango', 'especie')->where('d.scientific_name_visible', true);
            $rows = (clone $query)->select('t.nombre_cientifico')->distinct()->orderBy('t.nombre_cientifico')->limit(8)->pluck('nombre_cientifico')->all();
            $count = (clone $query)->distinct()->count('t.nombre_cientifico');
            $text = 'Hay '.$count.' '.($count === 1 ? 'especie publicada' : 'especies publicadas').$de.'. '.implode('; ', $rows).($count > 8 ? '; se muestran las primeras 8.' : '.');
            return $this->resultado($text, 'catalogo.species', $entities, $count, $rows, $options);
        }
        if (preg_match('/\b(cuantos?|numero|total|tienen|existe|hay registros)\b/', $normal)) {
            return $this->resultado('Hay '.$total.' '.($total === 1 ? 'registro publicado' : 'registros publicados').$de.' en el catálogo.', 'catalogo.count', $entities, $total, [], $options);
        }
        $rows = (clone $query)->where('d.occurrence_id_visible', true)->where('d.scientific_name_visible', true)->select('e.occurrence_id', 't.nombre_cientifico')
            ->orderBy('e.occurrence_id')->limit(10)->get()
            ->map(static fn ($row) => $row->occurrence_id.' — '.$row->nombre_cientifico)->all();
        $text = 'Encontré '.$total.' '.($total === 1 ? 'registro publicado' : 'registros publicados').$de.'. Primeros resultados: '.implode('; ', $rows).($total > 10 ? '; se muestran los primeros 10.' : '.');
        return $this->resultado($text, 'catalogo.search', $entities, $total, $rows, $options);
    }

    private function publicos(): Builder
    {
        return DB::table('taxonomia.especimenes as e')
            ->join('taxonomia.taxones as t', 't.id', '=', 'e.taxon_id')
            ->join('divulgacion.especimenes_divulgables as d', 'd.especimen_id', '=', 'e.id')
            ->where('d.publicado', true);
    }

    /** Solo propone nombres que aparecen en registros públicos; nunca cambia el filtro en silencio. */
    private function sugerirTaxonPublico(string $name): array
    {
        if (DB::getDriverName() !== 'pgsql' || mb_strlen($name) < 4 || mb_strlen($name) > 80) return [];
        $sql = <<<'SQL'
            WITH nombres AS (
                SELECT t.nombre_cientifico AS nombre FROM taxonomia.especimenes e
                JOIN taxonomia.taxones t ON t.id = e.taxon_id
                JOIN divulgacion.especimenes_divulgables d ON d.especimen_id = e.id
                WHERE d.publicado = true AND d.scientific_name_visible = true AND d.occurrence_id_visible = true
                UNION
                SELECT p.nombre_cientifico AS nombre FROM taxonomia.especimenes e
                JOIN taxonomia.taxones t ON t.id = e.taxon_id
                JOIN taxonomia.taxones p ON p.id = t.padre_id
                JOIN divulgacion.especimenes_divulgables d ON d.especimen_id = e.id
                WHERE d.publicado = true AND d.scientific_name_visible = true AND d.occurrence_id_visible = true
            )
            SELECT nombre FROM nombres WHERE similarity(lower(nombre), lower(?)) >= 0.45
              AND lower(nombre) <> lower(?) ORDER BY similarity(lower(nombre), lower(?)) DESC, nombre LIMIT 10
            SQL;
        $matches = array_filter(DB::select($sql, [$name, $name, $name]), static fn ($row) =>
            levenshtein(mb_strtolower($name), mb_strtolower($row->nombre)) <= 2);
        return array_slice(array_map(static fn ($row) => $row->nombre, $matches), 0, 3);
    }

    private function porCodigo(string $code, array $options): array
    {
        $rows = $this->publicos()->where('d.occurrence_id_visible', true)->where('d.scientific_name_visible', true)
            ->whereRaw('(lower(e.occurrence_id) = lower(?) OR lower(e.codigo_catalogo) = lower(?))', [$code, $code])
            ->select('e.occurrence_id', 't.nombre_cientifico')->orderBy('e.occurrence_id')->limit(10)->get();
        $items = $rows->map(static fn ($row) => $row->occurrence_id.' — '.$row->nombre_cientifico)->all();
        $text = $rows->isEmpty() ? 'No encontré registros públicos con ese código en el catálogo.'
            : 'Encontré '.count($items).' '.(count($items) === 1 ? 'registro publicado' : 'registros publicados').': '.implode('; ', $items).'.';
        return $this->resultado($text, 'catalogo.code', ['codigo' => $code], count($items), $items, $options);
    }

    private function localidades(Builder $query, array $entities, array $options): array
    {
        $rows = (clone $query)->where(function (Builder $q): void {
            $q->where(fn (Builder $inner) => $inner->where('d.locality_name_visible', true)->whereNotNull('e.locality_name'))
                ->orWhere(fn (Builder $inner) => $inner->where('d.state_province_visible', true)->whereNotNull('e.state_province'));
        })->selectRaw('CASE WHEN d.locality_name_visible THEN e.locality_name END AS localidad, CASE WHEN d.state_province_visible THEN e.state_province END AS provincia')
            ->distinct()->limit(8)->get();
        $items = $rows->map(static fn ($row) => implode(', ', array_filter([$row->localidad, $row->provincia])))->filter()->all();
        $text = $items === [] ? 'No encontré localidades publicadas para esa búsqueda.'
            : 'Localidades publicadas: '.implode('; ', $items).'.';
        return $this->resultado($text, 'catalogo.localities', $entities, count($items), $items, $options);
    }

    private function familias(array $options): array
    {
        $sql = <<<'SQL'
            WITH RECURSIVE linaje AS (
                SELECT e.id AS especimen_id, t.id AS taxon_id, t.padre_id, t.rango, t.nombre_cientifico, 0 AS profundidad
                FROM taxonomia.especimenes e
                JOIN taxonomia.taxones t ON t.id = e.taxon_id
                JOIN divulgacion.especimenes_divulgables d ON d.especimen_id = e.id
                WHERE d.publicado = true AND d.occurrence_id_visible = true AND d.scientific_name_visible = true
                  AND d.family_visible = true AND e.occurrence_id IS NOT NULL
                UNION ALL
                SELECT l.especimen_id, p.id, p.padre_id, p.rango, p.nombre_cientifico, l.profundidad + 1
                FROM linaje l JOIN taxonomia.taxones p ON p.id = l.padre_id WHERE l.profundidad < 15
            )
            SELECT nombre_cientifico, COUNT(DISTINCT especimen_id) AS registros FROM linaje WHERE rango = 'familia'
            GROUP BY nombre_cientifico ORDER BY registros DESC, nombre_cientifico LIMIT 10
            SQL;
        $rows = array_map(static fn ($row) => $row->nombre_cientifico.' ('.$row->registros.' registros)', DB::select($sql));
        $text = $rows === [] ? 'No encontré familias con registros publicados.'
            : 'Estas familias tienen registros publicados: '.implode('; ', $rows).'. Puedes abrir el catálogo para ver más.';
        return $this->resultado($text, 'catalogo.families', [], count($rows), $rows, $options);
    }

    private function generos(string $family, array $options): array
    {
        $sql = <<<'SQL'
            WITH RECURSIVE descendientes AS (
                SELECT id, rango, nombre_cientifico, NULL::uuid AS genero_id, 0 AS profundidad
                FROM taxonomia.taxones WHERE lower(nombre_cientifico) = lower(?) AND rango = 'familia'
                UNION ALL
                SELECT t.id, t.rango, t.nombre_cientifico,
                       CASE WHEN t.rango = 'genero' THEN t.id ELSE d.genero_id END, d.profundidad + 1
                FROM taxonomia.taxones t JOIN descendientes d ON t.padre_id = d.id WHERE d.profundidad < 15
            )
            SELECT DISTINCT g.nombre_cientifico FROM descendientes x
            JOIN taxonomia.especimenes e ON e.taxon_id = x.id
            JOIN divulgacion.especimenes_divulgables v ON v.especimen_id = e.id
            JOIN taxonomia.taxones g ON g.id = x.genero_id
            WHERE x.rango = 'especie' AND v.publicado = true AND v.occurrence_id_visible = true
              AND v.scientific_name_visible = true AND v.genus_visible = true
              AND e.occurrence_id IS NOT NULL
            ORDER BY g.nombre_cientifico LIMIT 10
            SQL;
        $rows = array_map(static fn ($row) => $row->nombre_cientifico, DB::select($sql, [$family]));
        $text = $rows === [] ? 'No encontré géneros publicados dentro de '.$family.'.'
            : 'Géneros publicados dentro de '.$family.': '.implode('; ', $rows).'.';
        return $this->resultado($text, 'catalogo.genera', ['taxon' => $family], count($rows), $rows, $options);
    }

    private function resultado(string $text, string $intent, array $entities, int $total, array $rows, array $options): array
    {
        return ['texto' => $text, 'opciones' => $options, 'intent' => $intent, 'fuente' => 'catalogo',
            'confianza' => 'HIGH', 'confianza_valor' => 1.0, 'entidades' => $entities,
            'datos' => ['total' => $total, 'filas' => $rows]];
    }
}
