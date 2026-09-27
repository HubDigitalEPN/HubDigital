<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot;

use Illuminate\Support\Facades\DB;

/** Intercambio portable de conocimiento; la importación deja los nodos en borrador. */
final class TransferenciaConocimientoChat
{
    public function exportar(): string
    {
        $nodes = DB::table('divulgacion.chat_nodes')->orderBy('id')->get();
        $slugs = $nodes->pluck('slug', 'id')->all();
        $aliases = DB::table('divulgacion.chat_aliases')->get()->groupBy('node_id');
        $variants = DB::table('divulgacion.chat_variants')->get()->groupBy('node_id');
        $drafts = DB::table('divulgacion.chat_node_drafts')->pluck('payload', 'node_id');
        $items = [];
        foreach ($nodes as $node) {
            $item = ['slug' => $node->slug, 'parent' => $slugs[$node->parent_id] ?? null,
                'title' => $node->title, 'answer' => $node->answer, 'action' => $node->action,
                'position' => (int) $node->position, 'needs_review' => (bool) $node->needs_curator_review,
                'aliases' => $aliases->get($node->id)?->pluck('phrase')->all() ?? [],
                'variants' => $variants->get($node->id)?->map(static fn ($v) =>
                    ['text' => $v->text, 'weight' => (int) $v->weight, 'active' => (bool) $v->active])->all() ?? []];
            if (isset($drafts[$node->id])) {
                $draft = json_decode($drafts[$node->id], true, flags: JSON_THROW_ON_ERROR);
                $item['draft'] = ['parent' => $slugs[$draft['parent_id']] ?? null,
                    'title' => $draft['title'], 'answer' => $draft['answer'], 'action' => $draft['action'],
                    'position' => $draft['position'], 'needs_review' => $draft['needs_curator_review'],
                    'aliases' => $draft['aliases'], 'variants' => $draft['variants']];
            }
            $items[] = $item;
        }
        $settings = DB::table('divulgacion.chat_settings')->where('key', 'ranking')->value('value');
        return json_encode(['format' => 'hubdigital-chat-knowledge', 'version' => 1,
            'nodes' => $items, 'synonyms' => DB::table('divulgacion.chat_synonyms')->orderBy('term')->get(['term', 'canonical'])->toArray(),
            'ranking' => $settings ? json_decode($settings, true, flags: JSON_THROW_ON_ERROR) : config('chatbot.ranking')],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    public function previsualizar(string $json): array
    {
        if (strlen($json) > 2_000_000) throw new \InvalidArgumentException('El JSON supera 2 MB.');
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($data) || ($data['format'] ?? null) !== 'hubdigital-chat-knowledge' || ($data['version'] ?? null) !== 1
            || ! is_array($data['nodes'] ?? null) || count($data['nodes']) > 250 || ! is_array($data['synonyms'] ?? null)
            || count($data['synonyms']) > 500 || ! is_array($data['ranking'] ?? null)) {
            throw new \InvalidArgumentException('Estructura o versión de conocimiento inválida.');
        }
        $slugs = [];
        foreach ($data['nodes'] as $node) {
            if (! is_array($node) || ! preg_match('/^[a-z0-9_]{2,100}$/', (string) ($node['slug'] ?? ''))
                || isset($slugs[$node['slug']]) || ! $this->nodoValido($node)) {
                throw new \InvalidArgumentException('Nodo o identificador duplicado o inválido.');
            }
            $slugs[$node['slug']] = true;
            if (isset($node['draft']) && (! is_array($node['draft']) || ! $this->nodoValido($node['draft']))) {
                throw new \InvalidArgumentException('Borrador inválido.');
            }
        }
        foreach ($data['nodes'] as $node) {
            foreach ([$node['parent'] ?? null, $node['draft']['parent'] ?? null] as $parent) {
                if ($parent !== null && (! is_string($parent) || ! isset($slugs[$parent]) || $parent === $node['slug'])) {
                    throw new \InvalidArgumentException('El árbol contiene un padre inexistente o propio.');
                }
            }
        }
        $parents = [];
        foreach ($data['nodes'] as $node) $parents[$node['slug']] = $node['draft']['parent'] ?? $node['parent'] ?? null;
        foreach (array_keys($parents) as $slug) {
            $seen = [];
            for ($current = $slug; $current !== null; $current = $parents[$current] ?? null) {
                if (isset($seen[$current])) throw new \InvalidArgumentException('El árbol contiene un ciclo.');
                $seen[$current] = true;
            }
        }
        $terms = [];
        foreach ($data['synonyms'] as $item) {
            if (! is_array($item)) throw new \InvalidArgumentException('Sinónimo inválido.');
            $term = $item['term'] ?? null;
            if (! is_string($term) || ! preg_match('/^[a-z0-9 ]{2,80}$/', $term) || isset($terms[$term])
                || ! is_string($item['canonical'] ?? null) || mb_strlen($item['canonical']) > 80) {
                throw new \InvalidArgumentException('Sinónimo duplicado o inválido.');
            }
            $terms[$term] = true;
        }
        $defaults = config('chatbot.ranking');
        $weights = $data['ranking']['weights'] ?? null;
        $thresholds = $data['ranking']['thresholds'] ?? null;
        if (! is_array($weights) || ! is_array($thresholds)
            || array_diff_key($weights, $defaults['weights']) !== []
            || array_diff_key($thresholds, $defaults['thresholds']) !== []
            || array_diff_key($defaults['weights'], $weights) !== []) throw new \InvalidArgumentException('Configuración de ranking inválida.');
        foreach (array_merge($weights, $thresholds) as $value) {
            if (! is_numeric($value) || $value < 0 || $value > 1) throw new \InvalidArgumentException('Peso o umbral fuera de rango.');
        }
        if (array_sum($weights) <= 0) throw new \InvalidArgumentException('Los pesos no pueden sumar cero.');
        $thresholds = array_replace($defaults['thresholds'], $thresholds);
        if ($thresholds['high'] <= $thresholds['medium'] || $thresholds['medium'] <= $thresholds['unknown']
            || $thresholds['ambiguous_min'] < $thresholds['unknown']) {
            throw new \InvalidArgumentException('Los umbrales de confianza no conservan su orden.');
        }
        return ['nodes' => count($data['nodes']), 'synonyms' => count($data['synonyms']),
            'new_nodes' => count(array_diff(array_keys($slugs), DB::table('divulgacion.chat_nodes')->whereIn('slug', array_keys($slugs))->pluck('slug')->all())),
            'ranking' => true, 'hash' => hash('sha256', $json)];
    }

    public function importar(string $json, string $actor): array
    {
        $preview = $this->previsualizar($json);
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        DB::transaction(function () use ($data, $actor): void {
            $ids = DB::table('divulgacion.chat_nodes')->pluck('id', 'slug')->all();
            foreach ($data['nodes'] as $node) {
                if (isset($ids[$node['slug']])) continue;
                $ids[$node['slug']] = DB::table('divulgacion.chat_nodes')->insertGetId([
                    'slug' => $node['slug'], 'title' => $node['title'], 'answer' => $node['answer'],
                    'status' => 'draft', 'needs_curator_review' => true, 'position' => $node['position'],
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            foreach ($data['nodes'] as $node) {
                $source = $node['draft'] ?? $node;
                $id = $ids[$node['slug']];
                $parent = $source['parent'] ?? null;
                $payload = json_encode(['parent_id' => $parent === null ? null : $ids[$parent],
                    'title' => $source['title'], 'answer' => $source['answer'], 'action' => $source['action'],
                    'position' => $source['position'], 'needs_curator_review' => $source['needs_review'],
                    'aliases' => $source['aliases'], 'variants' => $source['variants']], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                DB::table('divulgacion.chat_node_drafts')->updateOrInsert(['node_id' => $id],
                    ['payload' => $payload, 'edited_by' => $actor, 'updated_at' => now()]);
                $version = (int) DB::table('divulgacion.chat_nodes')->where('id', $id)->value('version');
                DB::table('divulgacion.chat_node_revisions')->insert(['node_id' => $id, 'version' => $version + 1,
                    'event' => 'imported_draft', 'actor_id' => $actor, 'payload' => $payload, 'created_at' => now()]);
            }
            foreach ($data['synonyms'] as $item) DB::table('divulgacion.chat_synonyms')->updateOrInsert(['term' => $item['term']], ['canonical' => $item['canonical']]);
            $settings = $data['ranking'];
            $settings['thresholds'] = array_replace(config('chatbot.ranking.thresholds'), $settings['thresholds']);
            $settings['confidence_formula'] = config('chatbot.ranking.confidence_formula');
            $ranking = json_encode($settings, JSON_THROW_ON_ERROR);
            DB::table('divulgacion.chat_settings')->updateOrInsert(['key' => 'ranking'], ['value' => $ranking, 'updated_at' => now()]);
            DB::table('divulgacion.chat_ranking_versions')->insert(['settings' => $ranking, 'actor_id' => $actor,
                'comment' => 'Importación del árbol de conocimiento', 'created_at' => now()]);
        });
        return $preview;
    }

    private function nodoValido(array $node): bool
    {
        if (! is_string($node['title'] ?? null) || trim($node['title']) === '' || mb_strlen($node['title']) > 160
            || ! is_string($node['answer'] ?? null) || trim($node['answer']) === '' || mb_strlen($node['answer']) > 3000
            || ! in_array($node['action'] ?? null, [null, 'DEPOSIT_LINK', 'MY_REQUESTS_LINK', 'CATALOG_LINK', 'LOGIN_LINK', 'CONTACT_LINK'], true)
            || ! is_int($node['position'] ?? null) || $node['position'] < 0 || $node['position'] > 999
            || ! is_bool($node['needs_review'] ?? null) || ! is_array($node['aliases'] ?? null) || count($node['aliases']) > 30
            || ! is_array($node['variants'] ?? null) || count($node['variants']) > 10) return false;
        $aliases = [];
        foreach ($node['aliases'] as $phrase) {
            if (! is_string($phrase) || trim($phrase) === '' || mb_strlen($phrase) > 240 || isset($aliases[$phrase])) return false;
            $aliases[$phrase] = true;
        }
        $variants = [];
        foreach ($node['variants'] as $variant) {
            if (! is_array($variant) || ! is_string($variant['text'] ?? null) || trim($variant['text']) === ''
                || mb_strlen($variant['text']) > 3000 || ! is_int($variant['weight'] ?? null)
                || $variant['weight'] < 1 || $variant['weight'] > 10 || ! is_bool($variant['active'] ?? null)
                || isset($variants[$variant['text']])) return false;
            $variants[$variant['text']] = true;
        }
        return true;
    }
}
