<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\EvaluadorCorpusChat;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\CorpusChat;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\TextoChat;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ConocimientoPortal;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\RankingIntencionesChat;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (($argv[1] ?? '') === '--experiment') {
    $evaluator = $app->make(EvaluadorCorpusChat::class);
    $results = [];
    foreach (['A_flat' => false, 'B_structured' => true] as $name => $structured) {
        $r = $evaluator->evaluar('calibration', null, null, $structured);
        $results[$name] = array_intersect_key($r, array_flip(['total', 'top1', 'top2', 'top3', 'top5',
            'macro_f1', 'coverage', 'automatic_precision', 'ood_correct', 'ood_total', 'unknown_incorrect',
            'clarification_correct', 'clarification_unnecessary']));
    }
    echo json_encode(['sizes' => CorpusChat::tamanos(), 'variants' => $results],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

if (($argv[1] ?? '') === '--cross-validate') {
    $cases = array_merge(CorpusChat::particion('train'), CorpusChat::particion('calibration'));
    $byClass = [];
    foreach ($cases as $case) $byClass[$case['expected']][] = $case;
    $folds = array_fill(0, 5, []);
    foreach ($byClass as $rows) {
        usort($rows, static fn ($a, $b) => crc32($a['question']) <=> crc32($b['question']));
        foreach ($rows as $i => $row) $folds[$i % 5][] = $row;
    }
    $reports = [];
    foreach ($folds as $i => $fold) {
        $r = $app->make(EvaluadorCorpusChat::class)->evaluarCasos($fold);
        $reports[] = ['fold' => $i + 1, 'cases' => count($fold), 'top1' => $r['top1'], 'macro_f1' => $r['macro_f1']];
    }
    $aggregate = [];
    foreach (['top1', 'macro_f1'] as $key) {
        $values = array_column($reports, $key);
        $mean = array_sum($values) / count($values);
        $aggregate[$key] = ['mean' => round($mean, 4),
            'sd' => round(sqrt(array_sum(array_map(static fn ($v) => ($v - $mean) ** 2, $values)) / count($values)), 4)];
    }
    echo json_encode(['folds' => $reports, 'aggregate' => $aggregate], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    exit;
}

if (($argv[1] ?? '') === '--curve') {
    $evaluator = $app->make(EvaluadorCorpusChat::class);
    $curve = [];
    foreach ([.30, .40, .50, .60, .65] as $medium) {
        $thresholds = array_replace(config('chatbot.ranking.thresholds'), ['medium' => $medium]);
        $r = $evaluator->evaluar('calibration', null, $thresholds);
        $curve[] = ['threshold' => $medium, 'coverage' => $r['coverage'],
            'precision' => $r['automatic_precision'], 'clarification_unknown' => round(1 - $r['coverage'], 4)];
    }
    echo json_encode($curve, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    exit;
}

if (($argv[1] ?? '') === '--distribution') {
    $names = ['train', 'calibration', 'test', 'test_final', 'challenge'];
    $counts = [];
    foreach ($names as $name) {
        foreach (CorpusChat::particion($name) as $case) {
            $label = $case['expected'];
            $counts[$label][$name] = ($counts[$label][$name] ?? 0) + 1;
        }
    }
    ksort($counts);
    echo json_encode(['sizes' => CorpusChat::tamanos(), 'distribution' => $counts],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

if (($argv[1] ?? '') === '--leakage') {
    $normalizer = $app->make(TextoChat::class);
    $development = array_merge(CorpusChat::particion('train'), CorpusChat::particion('calibration'));
    $holdouts = array_merge(CorpusChat::particion('test'), CorpusChat::particion('test_final'));
    $pairs = [];
    foreach ($holdouts as $holdout) {
        $a = $normalizer->normalizar($holdout['question']);
        if (strlen($a) < 12) continue;
        foreach ($development as $candidate) {
            $b = $normalizer->normalizar($candidate['question']);
            similar_text($a, $b, $similarity);
            if ($similarity >= 85) $pairs[] = ['holdout' => $holdout['question'],
                'development' => $candidate['question'], 'similarity' => round($similarity, 1)];
        }
    }
    echo json_encode(['similar_pairs' => $pairs], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

if (($argv[1] ?? '') === '--legacy') {
    $knowledge = $app->make(ConocimientoPortal::class);
    $errors = [];
    foreach (CorpusChat::casos() as [$question, $expected]) {
        $actual = $knowledge->responder($question, registrar: false)['intent'] ?? 'UNKNOWN';
        if ($actual !== $expected) $errors[] = compact('question', 'expected', 'actual');
    }
    echo json_encode($errors, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

if (($argv[1] ?? '') === '--sample') {
    $ranker = $app->make(RankingIntencionesChat::class);
    $knowledge = $app->make(ConocimientoPortal::class);
    $examples = [];
    foreach (['q papeles necesito para dejar unas muestras', 'no quiero donar, quiero depositar'] as $question) {
        $ranking = $ranker->evaluar($question);
        $examples[] = ['question' => $question, 'features' => $ranking['features'],
            'candidates' => array_map(static fn ($c) => ['slug' => $c['slug'], 'intent' => $c['intent'],
                'score' => $c['score'], 'components' => $c['components']], array_slice($ranking['candidatos'], 0, 3)),
            'confidence' => $ranker->confianza($ranking),
            'answer' => $knowledge->responder($question, registrar: false)['intent'] ?? 'UNKNOWN'];
    }
    echo json_encode($examples, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

if (($argv[1] ?? '') === '--final-once') {
    $path = dirname(__DIR__, 2).'/Modules/CatalogoPublico/docs/chat-fourth-evaluation.json';
    if (file_exists($path)) throw new RuntimeException('TEST_FINAL ya se ejecutó; consulta el informe guardado.');
    $evaluator = $app->make(EvaluadorCorpusChat::class);
    $result = ['test_final' => $evaluator->evaluar('test_final'), 'challenge' => $evaluator->evaluar('challenge')];
    $json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    if (file_put_contents($path, $json, LOCK_EX) === false) throw new RuntimeException('No se pudo guardar la evaluación final.');
    echo 'Evaluación final única guardada en '.$path.PHP_EOL;
    exit;
}

$nodes = DB::table('divulgacion.chat_nodes')->where('status', 'published')
    ->get(['id', 'slug', 'parent_id', 'title', 'action', 'answer']);
$lookup = $nodes->keyBy('id');
$tree = $nodes->map(static fn ($n) => [
    'slug' => $n->slug, 'parent' => $lookup[$n->parent_id]->slug ?? null,
    'title' => $n->title, 'action' => $n->action, 'answer' => $n->answer,
])->all();
$report = $app->make(EvaluadorCorpusChat::class)->evaluar('calibration');
echo json_encode(['tree' => $tree, 'summary' => [$report['top1'], $report['top2'], $report['macro_f1']],
    'errors' => $report['errors']], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
