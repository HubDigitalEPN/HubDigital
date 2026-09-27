<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot;

use Illuminate\Support\Facades\DB;

/** Evaluación local, sin escritura de usos ni mensajes; TEST no interviene en la calibración. */
final class EvaluadorCorpusChat
{
    public function __construct(
        private readonly RankingIntencionesChat $ranker,
        private readonly ConversacionBasica $social,
        private readonly ConsultaCatalogoPublico $catalog,
        private readonly SenalesIntencionChat $senales,
    ) {}

    public function evaluar(string $split, ?array $weights = null, ?array $thresholds = null,
        bool $estructurado = true): array
    {
        return $this->resumir($this->observar($split, $estructurado), $weights, $thresholds);
    }

    /** Diagnóstico de particiones estratificadas; no altera conocimiento ni aprende del fold. */
    public function evaluarCasos(array $cases, bool $estructurado = true): array
    {
        return $this->resumir($this->observarCasos($cases, $estructurado));
    }

    /** Una sola lectura de TEST para comparar sin recalibrar después de observarlo. */
    public function compararFinal(array $weights, array $thresholds): array
    {
        $observations = $this->observar('test');
        return ['current' => $this->resumir($observations),
            'suggested' => $this->resumir($observations, $weights, $thresholds)];
    }

    public function calibrar(): array
    {
        $observations = $this->observar('calibration');
        $current = $this->resumir($observations);
        $ranking = null;
        foreach ($observations as $observation) {
            if ($observation['ranking'] !== null) {
                $ranking = $observation['ranking'];
                break;
            }
        }
        $base = $ranking['weights'] ?? config('chatbot.ranking.weights');
        $baseThresholds = $ranking['thresholds'] ?? config('chatbot.ranking.thresholds');
        $best = $current;
        $suggested = $base;
        $suggestedThresholds = $baseThresholds;
        foreach ([.75, 1.0, 1.25] as $lexical) {
            foreach ([.75, 1.0, 1.25] as $fuzzy) {
                foreach ([.75, 1.0, 1.25] as $keyword) {
                    foreach ([.5, 1.0, 1.5] as $statistical) {
                        $weights = $base;
                        foreach (['exact', 'alias'] as $name) $weights[$name] *= $lexical;
                        foreach (['fts', 'trigram'] as $name) $weights[$name] *= $fuzzy;
                        $weights['keyword'] *= $keyword;
                        foreach (['context', 'transition', 'historical', 'quality'] as $name) $weights[$name] *= $statistical;
                        $sum = array_sum($weights);
                        $weights = array_map(static fn ($value) => round($value / $sum, 5), $weights);
                        foreach ([.22, .28, .34] as $medium) {
                            foreach ([.10, .15] as $unknown) {
                                $thresholds = array_replace($baseThresholds, ['medium' => $medium, 'unknown' => $unknown]);
                                $report = $this->resumir($observations, $weights, $thresholds);
                                if ($this->objetivo($report) > $this->objetivo($best) + .0001) {
                                    $best = $report;
                                    $suggested = $weights;
                                    $suggestedThresholds = $thresholds;
                                }
                            }
                        }
                    }
                }
            }
        }
        return ['current' => $current, 'suggested' => $best, 'current_weights' => $base,
            'suggested_weights' => $suggested, 'current_thresholds' => $baseThresholds,
            'suggested_thresholds' => $suggestedThresholds, 'combinations' => 486];
    }

    private function observar(string $split, bool $estructurado = true): array
    {
        return $this->observarCasos(CorpusChat::particion($split), $estructurado);
    }

    private function observarCasos(array $cases, bool $estructurado = true): array
    {
        $observations = [];
        $contextSlugs = array_filter(array_column($cases, 'context'));
        $contextIds = $contextSlugs === [] ? [] : DB::table('divulgacion.chat_nodes')
            ->whereIn('slug', $contextSlugs)->pluck('id', 'slug')->all();
        foreach ($cases as $case) {
            $social = $this->social->responder($case['question']);
            $catalog = $social === null ? $this->catalog->responder($case['question']) : null;
            $ranking = $social === null && $catalog === null
                ? $this->ranker->evaluar($case['question'], $contextIds[$case['context']] ?? null, $estructurado) : null;
            $observations[] = ['case' => $case, 'fixed' => $social !== null
                ? (($social['intent'] ?? null) === 'saludo' ? 'saludo' : 'conversacion')
                : ($catalog !== null ? 'catalogo' : null),
                'ranking' => $ranking];
        }
        return $observations;
    }

    private function resumir(array $observations, ?array $weights = null, ?array $thresholds = null): array
    {
        $rows = [];
        $matrix = [];
        $bins = array_fill_keys(['0.90-1.00', '0.80-0.89', '0.70-0.79', '0.60-0.69', '0.50-0.59', '<0.50'], ['cases' => 0, 'correct' => 0]);
        $correct = $topTwo = $topThree = $topFive = $oodCorrect = $oodTotal = 0;
        $automatic = $automaticCorrect = $unknownIncorrect = $clarificationCorrect = $clarificationUnnecessary = $highIncorrect = 0;
        $errorCategories = [];
        foreach ($observations as $observation) {
            $case = $observation['case'];
            $case['expected'] = $this->senales->intencion($case['expected']);
            $ranking = $observation['ranking'];
            if ($ranking !== null && $weights !== null) {
                $ranking = $this->ranker->reponderar($ranking, $weights);
            }
            if ($ranking !== null && $thresholds !== null) {
                $ranking['thresholds'] = $thresholds;
            }
            $confidence = $ranking === null ? null : $this->ranker->confianza($ranking);
            $best = $ranking['candidatos'][0] ?? null;
            $second = $ranking['candidatos'][1] ?? null;
            $actual = $observation['fixed'] ?? ($best === null ||
                (in_array($confidence['nivel'], ['UNKNOWN', 'LOW'], true) && ! $confidence['ambiguo'])
                    ? 'UNKNOWN' : ($confidence['ambiguo'] ? 'ambiguous' : $best['intent']));
            $hit = $actual === $case['expected'];
            $correct += (int) $hit;
            $topTwo += (int) ($hit || ($second !== null && $second['intent'] === $case['expected']));
            $candidateSlugs = array_column(array_slice($ranking['candidatos'] ?? [], 0, 5), 'intent');
            $topThree += (int) ($hit || in_array($case['expected'], array_slice($candidateSlugs, 0, 3), true));
            $topFive += (int) ($hit || in_array($case['expected'], $candidateSlugs, true));
            if (! in_array($actual, ['UNKNOWN', 'ambiguous'], true)) {
                $automatic++;
                $automaticCorrect += (int) $hit;
            }
            if ($actual === 'UNKNOWN' && $case['expected'] !== 'UNKNOWN') $unknownIncorrect++;
            if ($actual === 'ambiguous') {
                $clarificationCorrect += (int) $hit;
                $clarificationUnnecessary += (int) ! $hit;
            }
            if (($confidence['nivel'] ?? null) === 'HIGH' && ! $hit) $highIncorrect++;
            if (! $hit) {
                $category = $case['expected'] === 'UNKNOWN' ? 'OUT_OF_DOMAIN'
                    : ($case['context'] !== null ? 'CONTEXT'
                    : (($ranking['negado'] ?? '') !== '' ? 'NEGATION'
                    : ($actual === 'ambiguous' ? 'AMBIGUITY'
                    : ($actual === 'UNKNOWN' && in_array($case['expected'], $candidateSlugs, true) ? 'UNKNOWN_THRESHOLD'
                    : (! in_array($case['expected'], $candidateSlugs, true) ? 'CANDIDATE_GENERATION' : 'RERANKING')))));
                $errorCategories[$category] = ($errorCategories[$category] ?? 0) + 1;
            }
            $matrix[$case['expected']][$actual] = ($matrix[$case['expected']][$actual] ?? 0) + 1;
            if ($case['expected'] === 'UNKNOWN') {
                $oodTotal++;
                $oodCorrect += (int) $hit;
            }
            if ($confidence !== null) {
                $value = $confidence['valor'];
                $bin = $value >= .9 ? '0.90-1.00' : ($value >= .8 ? '0.80-0.89' : ($value >= .7 ? '0.70-0.79'
                    : ($value >= .6 ? '0.60-0.69' : ($value >= .5 ? '0.50-0.59' : '<0.50'))));
                $bins[$bin]['cases']++;
                $bins[$bin]['correct'] += (int) $hit;
            }
            $components = $best['components'] ?? [];
            arsort($components);
            $rows[] = ['question' => $case['question'], 'expected' => $case['expected'], 'actual' => $actual,
                'score' => $best['score'] ?? 0, 'second' => $second['intent'] ?? null, 'second_score' => $second['score'] ?? 0,
                'confidence' => $confidence['valor'] ?? null, 'level' => $confidence['nivel'] ?? null,
                'margin' => $confidence['margen'] ?? null, 'tag' => $case['tag'] ?? null,
                'reason' => array_key_first($components) ?? $observation['fixed'], 'correct' => $hit,
                'candidate_slugs' => $candidateSlugs, 'category' => $category ?? null];
            unset($category);
        }
        $classes = array_keys($matrix);
        $perClass = [];
        $macroPrecision = $macroRecall = $macroF1 = $weightedF1 = 0.0;
        foreach ($classes as $class) {
            $support = array_sum($matrix[$class]);
            $tp = $matrix[$class][$class] ?? 0;
            $predicted = array_sum(array_map(static fn ($row) => $row[$class] ?? 0, $matrix));
            $precision = $predicted > 0 ? $tp / $predicted : 0.0;
            $recall = $support > 0 ? $tp / $support : 0.0;
            $f1 = $precision + $recall > 0 ? 2 * $precision * $recall / ($precision + $recall) : 0.0;
            $perClass[$class] = ['support' => $support, 'precision' => round($precision, 4),
                'recall' => round($recall, 4), 'f1' => round($f1, 4)];
            $macroPrecision += $precision;
            $macroRecall += $recall;
            $macroF1 += $f1;
            $weightedF1 += $f1 * $support;
        }
        $total = count($rows);
        $confusions = [];
        foreach ($matrix as $expected => $actuals) {
            foreach ($actuals as $actual => $count) {
                if ($actual !== $expected) $confusions[] = compact('expected', 'actual', 'count');
            }
        }
        usort($confusions, static fn ($a, $b) => $b['count'] <=> $a['count']);
        $errors = array_values(array_filter($rows, static fn ($row) => ! $row['correct']));
        usort($errors, static fn ($a, $b) => ($b['confidence'] ?? 0) <=> ($a['confidence'] ?? 0));
        foreach ($bins as &$bin) $bin['accuracy'] = $bin['cases'] > 0 ? round($bin['correct'] / $bin['cases'], 4) : null;
        unset($bin);
        return ['total' => $total, 'correct' => $correct, 'top_two' => $topTwo,
            'top1' => $total ? round($correct / $total, 4) : 0,
            'top2' => $total ? round($topTwo / $total, 4) : 0,
            'top3' => $total ? round($topThree / $total, 4) : 0,
            'top5' => $total ? round($topFive / $total, 4) : 0,
            'coverage' => $total ? round($automatic / $total, 4) : 0,
            'automatic_precision' => $automatic ? round($automaticCorrect / $automatic, 4) : null,
            'automatic_cases' => $automatic, 'unknown_incorrect' => $unknownIncorrect,
            'clarification_correct' => $clarificationCorrect, 'clarification_unnecessary' => $clarificationUnnecessary,
            'high_incorrect' => $highIncorrect, 'error_categories' => $errorCategories,
            'macro_precision' => count($classes) ? round($macroPrecision / count($classes), 4) : 0,
            'macro_recall' => count($classes) ? round($macroRecall / count($classes), 4) : 0,
            'macro_f1' => count($classes) ? round($macroF1 / count($classes), 4) : 0,
            'weighted_f1' => $total ? round($weightedF1 / $total, 4) : 0,
            'per_class' => $perClass, 'matrix' => $matrix, 'rows' => $rows,
            'confusions' => array_slice($confusions, 0, 20),
            'errors' => array_slice($errors, 0, 20), 'bins' => $bins,
            'ood_correct' => $oodCorrect, 'ood_total' => $oodTotal];
    }

    private function objetivo(array $report): float
    {
        $falseOod = $report['ood_total'] - $report['ood_correct'];
        return $report['macro_f1'] - ($report['ood_total'] ? .25 * $falseOod / $report['ood_total'] : 0);
    }
}
