<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Contadores diarios, sin transcripciones ni procesos residentes. */
final class AnaliticaChat
{
    public function registrarMensaje(array $result, float $durationMs): string
    {
        $conversation = (int) session()->get('portal_chat_conversation_until', 0) < time() ? 1 : 0;
        session()->put('portal_chat_conversation_until', time() + 1800);
        $source = $result['fuente'] ?? 'unknown';
        $known = in_array($source, ['conocimiento', 'catalogo', 'conversacion', 'legacy'], true) ? 1 : 0;
        $unknown = in_array($source, ['unknown', 'aclaracion'], true) ? 1 : 0;
        $catalog = $source === 'catalogo' ? 1 : 0;
        $hasConfidence = $source === 'conocimiento' && isset($result['confianza_valor']);
        $confidence = $hasConfidence ? (float) $result['confianza_valor'] : 0.0;
        $level = $result['confianza'] ?? null;
        $high = $level === 'HIGH' ? 1 : 0;
        $medium = $level === 'MEDIUM' ? 1 : 0;
        $low = $level === 'LOW' ? 1 : 0;
        $clarification = $source === 'aclaracion' ? 1 : 0;
        $clarificationResolved = session()->get('portal_chat_waiting_clarification', false) && $known ? 1 : 0;
        $reformulated = session()->get('portal_chat_waiting_reformulation', false) && $known ? 1 : 0;
        session()->put('portal_chat_waiting_clarification', $clarification === 1);
        session()->put('portal_chat_waiting_reformulation', $unknown === 1);
        DB::statement(<<<'SQL'
            INSERT INTO divulgacion.chat_metrics_daily
                (day, conversations, messages, resolved, unknown, catalog_queries, confidence_sum, confidence_observations, latency_ms_sum, high, medium, low, clarifications, clarifications_resolved, reformulated, updated_at)
            VALUES (?, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON CONFLICT (day) DO UPDATE SET
                conversations = divulgacion.chat_metrics_daily.conversations + EXCLUDED.conversations,
                messages = divulgacion.chat_metrics_daily.messages + 1,
                resolved = divulgacion.chat_metrics_daily.resolved + EXCLUDED.resolved,
                unknown = divulgacion.chat_metrics_daily.unknown + EXCLUDED.unknown,
                catalog_queries = divulgacion.chat_metrics_daily.catalog_queries + EXCLUDED.catalog_queries,
                confidence_sum = divulgacion.chat_metrics_daily.confidence_sum + EXCLUDED.confidence_sum,
                confidence_observations = divulgacion.chat_metrics_daily.confidence_observations + EXCLUDED.confidence_observations,
                latency_ms_sum = divulgacion.chat_metrics_daily.latency_ms_sum + EXCLUDED.latency_ms_sum,
                high = divulgacion.chat_metrics_daily.high + EXCLUDED.high,
                medium = divulgacion.chat_metrics_daily.medium + EXCLUDED.medium,
                low = divulgacion.chat_metrics_daily.low + EXCLUDED.low,
                clarifications = divulgacion.chat_metrics_daily.clarifications + EXCLUDED.clarifications,
                clarifications_resolved = divulgacion.chat_metrics_daily.clarifications_resolved + EXCLUDED.clarifications_resolved,
                reformulated = divulgacion.chat_metrics_daily.reformulated + EXCLUDED.reformulated,
                updated_at = EXCLUDED.updated_at
            SQL, [now()->toDateString(), $conversation, $known, $unknown, $catalog, $confidence, (int) $hasConfidence, $durationMs,
                $high, $medium, $low, $clarification, $clarificationResolved, $reformulated, now()]);
        $messageId = (string) Str::uuid();
        $publicFilters = ($source === 'catalogo' && (int) ($result['datos']['total'] ?? 0) > 0)
            ? array_intersect_key($result['entidades'] ?? [], array_flip(['taxon', 'provincia', 'localidad', 'pais', 'codigo'])) : [];
        DB::table('divulgacion.chat_trace')->insert([
            'message_id' => $messageId,
            'session_hash' => hash_hmac('sha256', session()->getId(), (string) config('app.key')),
            'source' => Str::limit($source, 32, ''),
            'intent' => Str::limit((string) ($result['intent'] ?? 'UNKNOWN'), 100, ''),
            'node_id' => $result['node_id'] ?? null,
            'variant_id' => $result['variant_id'] ?? null,
            'catalog_action' => $source === 'catalogo' ? Str::limit((string) ($result['intent'] ?? ''), 100, '') : null,
            'public_filters' => $publicFilters === [] ? null : json_encode($publicFilters, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'confidence_level' => $level,
            'confidence_value' => isset($result['confianza_valor']) ? (float) $result['confianza_valor'] : null,
            'processing_ms' => $durationMs,
            'created_at' => now(),
        ]);
        if (random_int(1, 200) === 1) {
            DB::table('divulgacion.chat_trace')->where('created_at', '<', now()->subDays(7))->delete();
        }
        return $messageId;
    }

    public function registrarFeedback(bool $positive): void
    {
        $column = $positive ? 'feedback_positive' : 'feedback_negative';
        DB::table('divulgacion.chat_metrics_daily')->insertOrIgnore(['day' => now()->toDateString()]);
        DB::table('divulgacion.chat_metrics_daily')->where('day', now()->toDateString())->increment($column);
    }
}
