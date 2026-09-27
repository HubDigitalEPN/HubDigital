<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot;

use Illuminate\Support\Facades\DB;

/** Calcula evidencia comparable por intención; ninguna señal histórica decide sola. */
final class RankingIntencionesChat
{
    public function __construct(private readonly TextoChat $texto, private readonly SenalesIntencionChat $senales) {}

    public function evaluar(string $pregunta, ?int $anterior = null, bool $estructurado = true): array
    {
        $inicio = microtime(true);
        $contrast = $this->texto->contraste($pregunta);
        $normal = $contrast['positivo'];
        if ($normal === '') {
            return ['normal' => '', 'tokens' => [], 'sinonimos' => [], 'candidatos' => [], 'duracion_ms' => 0];
        }
        $nodes = DB::table('divulgacion.chat_nodes')->where('status', 'published')
            ->where('needs_curator_review', false)->orderBy('position')->limit(250)->get();
        if ($nodes->isEmpty()) {
            return ['normal' => $normal, 'tokens' => [], 'sinonimos' => [], 'candidatos' => [], 'duracion_ms' => 0];
        }
        $ids = $nodes->pluck('id')->all();
        $byId = $nodes->keyBy('id')->all();
        $features = $this->senales->analizar($normal, $contrast['negado'], $nodes->all(), $anterior);
        $aliases = DB::table('divulgacion.chat_aliases')->whereIn('node_id', $ids)->get()->groupBy('node_id');
        $synonyms = DB::table('divulgacion.chat_synonyms')->pluck('canonical', 'term')->all();
        $tokens = $this->texto->tokens($normal, $synonyms);
        $synonymsUsed = [];
        foreach (explode(' ', $normal) as $term) {
            if (isset($synonyms[$term])) {
                $synonymsUsed[$term] = $synonyms[$term];
            }
        }
        $transitions = $anterior === null ? [] : DB::table('divulgacion.chat_transitions')
            ->where('from_id', $anterior)->pluck('uses', 'to_id')->all();
        $transitionTotal = array_sum($transitions);
        $signals = collect();
        if (DB::getDriverName() === 'pgsql') {
            $hasTrgm = DB::table('pg_extension')->where('extname', 'pg_trgm')->exists();
            $sql = "SELECT n.id, ts_rank_cd(to_tsvector('spanish', n.title || ' ' || n.answer), plainto_tsquery('spanish', ?)) AS fts, "
                .($hasTrgm ? 'similarity(lower(n.title), lower(?))' : '0')." AS trigram
                  FROM divulgacion.chat_nodes n WHERE n.status = 'published' AND n.needs_curator_review = false LIMIT 250";
            $signals = collect(DB::select($sql, $hasTrgm ? [$normal, $normal] : [$normal]))->keyBy('id');
        }
        $settings = DB::table('divulgacion.chat_settings')->where('key', 'ranking')->value('value');
        $ranking = $settings ? json_decode($settings, true) : null;
        $weights = $ranking['weights'] ?? config('chatbot.ranking.weights');
        $totalUses = (int) $nodes->sum('uses');
        $candidates = [];
        foreach ($nodes as $node) {
            $phrases = array_merge([$node->title], $aliases->get($node->id)?->pluck('phrase')->all() ?? []);
            $exact = $alias = $keyword = 0.0;
            foreach ($phrases as $phrase) {
                $candidate = $this->texto->normalizar((string) $phrase);
                if ($candidate === '') {
                    continue;
                }
                if ($candidate === $normal) {
                    $exact = 1.0;
                    $alias = 1.0;
                } elseif (mb_strlen($candidate) >= 8 && str_contains($normal, $candidate)) {
                    $alias = max($alias, .82);
                } elseif (mb_strlen($normal) >= 7 && mb_strlen($candidate) >= 7) {
                    $alias = max($alias, max(0, 1 - levenshtein($normal, $candidate) / max(strlen($normal), strlen($candidate))) * .8);
                }
                $candidateTokens = $this->texto->tokens($candidate, $synonyms);
                $matched = count(array_intersect($tokens, $candidateTokens));
                $keyword = max($keyword, $matched / max(1, count($tokens)));
                if ($candidateTokens !== [] && $matched === count($candidateTokens)
                    && (count($candidateTokens) >= 2 || count($tokens) === 1)) {
                    $alias = max($alias, .72 + .12 * min(1, count($candidateTokens) / max(1, count($tokens))));
                }
            }
            $signal = $signals->get($node->id);
            $fts = min(1.0, max(0.0, (float) ($signal->fts ?? 0) * 3));
            $trigram = min(1.0, max(0.0, (float) ($signal->trigram ?? 0)));
            $context = $anterior !== null && ($node->parent_id == $anterior || $node->id == $anterior) ? 1.0 : 0.0;
            $transition = $transitionTotal > 0 ? ((int) ($transitions[$node->id] ?? 0) + 1) / ($transitionTotal + count($ids)) : 0.0;
            $prior = ((int) $node->uses + 1) / ($totalUses + count($ids));
            $quality = ((int) $node->helpful + 1) / ((int) $node->helpful + (int) $node->unhelpful + 2);
            if (! config('chatbot.statistical_ranking', true)) {
                $context = $transition = $prior = $quality = 0.0;
            }
            $components = compact('exact', 'alias', 'fts', 'trigram', 'keyword', 'context', 'transition', 'prior', 'quality');
            $components['historical'] = $prior;
            $score = 0.0;
            foreach ($weights as $name => $weight) {
                $score += (float) $weight * ($components[$name] ?? 0.0);
            }
            // Etapa 1: evidencia recuperada del nodo, aliases y texto.
            $candidates[] = ['id' => (int) $node->id, 'slug' => $node->slug, 'title' => $node->title,
                'node' => $node, 'base_score' => $score, 'components' => $components];
        }
        // Etapa 2: compatibilidad de dominio, tema, negación y contexto antes de decidir.
        foreach ($candidates as &$candidate) {
            $profile = $this->senales->perfil($candidate['node'], $byId);
            $bonus = $estructurado ? $this->senales->bonus($features, $profile) : 0.0;
            $candidate['components']['structural'] = $bonus;
            $candidate['profile'] = $profile;
            $candidate['intent'] = $this->senales->intencion($candidate['slug']);
            $candidate['score'] = round(min(1.0, max(0.0, $candidate['base_score'] + $bonus)), 4);
            unset($candidate['base_score']);
        }
        unset($candidate);
        usort($candidates, static fn ($a, $b) => $b['score'] <=> $a['score']);
        $clarificationCandidates = [];
        foreach ($estructurado ? $features['clarify_slugs'] : [] as $slug) {
            foreach ($candidates as $candidate) {
                if ($candidate['slug'] === $slug) $clarificationCandidates[] = $candidate;
            }
        }
        return ['normal' => $normal, 'negado' => $contrast['negado'], 'tokens' => $tokens, 'sinonimos' => $synonymsUsed,
            'candidatos' => $candidates, 'duracion_ms' => round((microtime(true) - $inicio) * 1000, 2),
            'features' => $features, 'clarification_candidates' => $clarificationCandidates,
            'mode' => $estructurado ? 'structured' : 'flat',
            'weights' => $weights,
            'thresholds' => $ranking['thresholds'] ?? config('chatbot.ranking.thresholds'),
            'confidence_formula' => $ranking['confidence_formula'] ?? config('chatbot.ranking.confidence_formula')];
    }

    /** Recalcula únicamente scores ya observados; la búsqueda de pesos no repite SQL. */
    public function reponderar(array $ranking, array $weights): array
    {
        foreach ($ranking['candidatos'] as &$candidate) {
            $score = 0.0;
            foreach ($weights as $name => $weight) {
                $score += (float) $weight * (float) ($candidate['components'][$name] ?? 0);
            }
            $score += (float) ($candidate['components']['structural'] ?? 0);
            $candidate['score'] = round(min(1.0, max(0.0, $score)), 4);
        }
        unset($candidate);
        usort($ranking['candidatos'], static fn ($a, $b) => $b['score'] <=> $a['score']);
        $ranking['weights'] = $weights;
        return $ranking;
    }

    public function confianza(array $ranking): array
    {
        $best = $ranking['candidatos'][0] ?? null;
        if (count($ranking['clarification_candidates'] ?? []) >= 2) {
            return ['nivel' => 'LOW', 'valor' => 0.0, 'margen' => 0.0, 'ambiguo' => true];
        }
        if ($best === null) {
            return ['nivel' => 'UNKNOWN', 'valor' => 0.0, 'margen' => 0.0, 'ambiguo' => false];
        }
        $second = $ranking['candidatos'][1]['score'] ?? 0.0;
        $margin = max(0.0, $best['score'] - $second);
        $evidence = max($best['components']['exact'], $best['components']['alias'], $best['components']['keyword'],
            $best['components']['fts'], $best['components']['trigram'], max(0, ($best['components']['structural'] ?? 0) * 3));
        $formula = $ranking['confidence_formula'];
        $confidence = min(1.0, $formula['score'] * $best['score']
            + $formula['margin'] * min(1.0, $margin / max(.01, $formula['margin_scale']))
            + $formula['evidence'] * $evidence);
        $thresholds = $ranking['thresholds'];
        $ambiguous = $second >= ($thresholds['ambiguous_min'] ?? .35) && $margin < ($thresholds['min_margin'] ?? .08);
        if ($ambiguous && ($ranking['candidatos'][1]['node']->parent_id ?? null) == $best['id']
            && $best['components']['alias'] > ($ranking['candidatos'][1]['components']['alias'] ?? 0)) {
            $ambiguous = false; // Pregunta general: el padre explica mejor que un subtipo cercano.
        }
        if ($ambiguous && $best['components']['context'] > ($ranking['candidatos'][1]['components']['context'] ?? 0)
            && $margin >= ($thresholds['contextual_min_margin'] ?? .02)) {
            $ambiguous = false;
        }
        $level = $confidence >= $thresholds['high'] && ! $ambiguous ? 'HIGH'
            : ($confidence >= $thresholds['medium'] ? 'MEDIUM' : ($best['score'] >= ($thresholds['unknown'] ?? .22) ? 'LOW' : 'UNKNOWN'));
        if ($level === 'HIGH' && ! config('chatbot.high_confidence_enabled', false)) $level = 'MEDIUM';
        return ['nivel' => $level, 'valor' => round($confidence, 4), 'margen' => round($margin, 4), 'ambiguo' => $ambiguous];
    }
}
