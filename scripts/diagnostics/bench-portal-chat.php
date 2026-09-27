<?php

declare(strict_types=1);

/** Banco local de lectura: no guarda estadísticas, contexto ni preguntas. */
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ConocimientoPortal;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ConsultaCatalogoPublico;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ConversacionBasica;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\CorpusChat;

$count = isset($argv[1]) ? max(1, min(1000, (int) $argv[1])) : 500;
$cases = CorpusChat::particion('train');
$social = app(ConversacionBasica::class);
$catalog = app(ConsultaCatalogoPublico::class);
$knowledge = app(ConocimientoPortal::class);
$samples = [];
$errors = 0;
$types = ['conversacion' => 0, 'catalogo' => 0, 'conocimiento' => 0, 'unknown' => 0];
for ($i = 0; $i < $count; $i++) {
    $question = $cases[$i % count($cases)]['question'];
    $start = hrtime(true);
    try {
        $result = $social->responder($question) ?? $catalog->responder($question)
            ?? $knowledge->responder($question, registrar: false);
        $source = $result['fuente'] ?? 'unknown';
        $types[$source] = ($types[$source] ?? 0) + 1;
    } catch (Throwable $error) {
        $errors++;
    }
    $samples[] = (hrtime(true) - $start) / 1_000_000;
}
sort($samples, SORT_NUMERIC);
$quantile = static fn (float $q): float => round($samples[min(count($samples) - 1, (int) ceil(count($samples) * $q) - 1)], 2);
echo json_encode(['messages' => $count, 'p50_ms' => $quantile(.5), 'p95_ms' => $quantile(.95),
    'p99_ms' => $quantile(.99), 'max_ms' => round(max($samples), 2), 'errors' => $errors,
    'sources' => $types, 'peak_memory_mb' => round(memory_get_peak_usage(true) / 1048576, 2)], JSON_THROW_ON_ERROR), PHP_EOL;
