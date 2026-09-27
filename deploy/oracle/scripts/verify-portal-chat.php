<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ConocimientoPortal;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ConsultaCatalogoPublico;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\CorpusChat;

require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    foreach (['chat_nodes', 'chat_aliases', 'chat_variants', 'chat_synonyms', 'chat_transitions', 'chat_unmatched', 'chat_settings', 'chat_metrics_daily', 'chat_ranking_versions', 'chat_node_drafts', 'chat_node_revisions', 'chat_trace'] as $table) {
        if (! DB::getSchemaBuilder()->hasTable('divulgacion.'.$table)) {
            throw new RuntimeException('Falta la tabla divulgacion.'.$table);
        }
    }
    if (! DB::getSchemaBuilder()->hasColumn('divulgacion.chat_metrics_daily', 'confidence_observations')) {
        throw new RuntimeException('Falta la columna de observaciones de confianza.');
    }
    foreach (['high', 'medium', 'low', 'clarifications', 'clarifications_resolved', 'reformulated'] as $column) {
        if (! DB::getSchemaBuilder()->hasColumn('divulgacion.chat_metrics_daily', $column)) {
            throw new RuntimeException('Falta la columna de calidad '.$column);
        }
    }
    if (! DB::getSchemaBuilder()->hasColumn('divulgacion.chat_nodes', 'published_at')
        || count(CorpusChat::particion('train')) < 150
        || count(CorpusChat::particion('test_final')) < 50) {
        throw new RuntimeException('Falta la publicación versionada o el corpus ampliado.');
    }
    $knowledge = $app->make(ConocimientoPortal::class);
    $hello = $knowledge->responder('hola', registrar: false);
    $docs = $knowledge->responder('¿Qué papeles tengo que mandar?', registrar: false);
    $donation = $knowledge->responder('quiero donar', registrar: false);
    $negatedDonation = $knowledge->responder('no es depósito, es donación', registrar: false);
    $ambiguous = $knowledge->responder('¿Qué requisitos hay?', registrar: false);
    $compound = $knowledge->responderCompuesta('Quiero hacer un depósito y saber qué documentos necesito');
    $catalog = $app->make(ConsultaCatalogoPublico::class)->responder('¿Tienen Inexistentius?');
    if (! str_contains($hello['texto'] ?? '', 'Hola')
        || ! str_contains($docs['texto'] ?? '', 'autorización')
        || ($donation['intent'] ?? null) !== 'donacion'
        || ($negatedDonation['intent'] ?? null) !== 'donacion'
        || ($ambiguous['fuente'] ?? null) !== 'aclaracion'
        || ($compound['intent'] ?? null) !== 'deposito+documentos'
        || ! str_contains($catalog['texto'] ?? '', 'No encontré registros publicados')) {
        throw new RuntimeException('La respuesta institucional o la consulta pública no coincidió con lo esperado.');
    }
    echo 'OK asistente: esquema versionado, corpus, saludo, donación, ambigüedad, pregunta compuesta y consulta pública; pico PHP '.round(memory_get_peak_usage(true) / 1048576, 1).' MiB.'.PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'NO OK asistente: '.$error->getMessage().PHP_EOL);
    exit(1);
}
