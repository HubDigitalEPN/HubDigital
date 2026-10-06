<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** Orquesta ranking, confianza, respuestas aprobadas y aprendizaje agregado. */
final class ConocimientoPortal
{
    public function __construct(private readonly RankingIntencionesChat $ranker, private readonly TextoChat $texto) {}

    /** Un saludo reconocido exactamente conserva la respuesta aprobada, sus variantes y valoración. */
    public function saludoPublicado(?int $anterior = null, array $variantesRecientes = []): ?array
    {
        $nodo = DB::table('divulgacion.chat_nodes')->where('slug', 'saludo')
            ->where('status', 'published')->where('needs_curator_review', false)->first();
        if ($nodo === null) return null;
        [$respuesta, $variante] = $this->elegirVariante((int) $nodo->id, $nodo->answer, $variantesRecientes);
        DB::table('divulgacion.chat_nodes')->where('id', $nodo->id)->increment('uses');
        if ($variante !== null) DB::table('divulgacion.chat_variants')->where('id', $variante)->increment('uses');
        if ($anterior !== null && DB::table('divulgacion.chat_nodes')->where('id', $anterior)->exists()) {
            DB::table('divulgacion.chat_transitions')->insertOrIgnore(['from_id' => $anterior, 'to_id' => $nodo->id, 'uses' => 0]);
            DB::table('divulgacion.chat_transitions')->where('from_id', $anterior)->where('to_id', $nodo->id)->increment('uses');
        }
        return ['texto' => $respuesta, 'opciones' => $this->opciones((string) $nodo->action, (int) $nodo->id),
            'node_id' => (int) $nodo->id, 'variant_id' => $variante, 'node_slug' => 'saludo', 'intent' => 'saludo',
            'fuente' => 'conocimiento', 'confianza' => 'HIGH', 'confianza_valor' => 1.0, 'entidades' => []];
    }

    /** Combina únicamente dos temas relacionados que ya tienen respuesta institucional publicada. */
    public function responderCompuesta(string $pregunta): ?array
    {
        $normal = $this->texto->contraste($pregunta)['positivo'];
        if (! preg_match('/\b(deposito|depositar)\b/', $normal)
            || ! preg_match('/\b(documentos?|papeles|permisos|archivos)\b/', $normal)
            || ! preg_match('/\b(y|ademas|tambien)\b/', $normal)) return null;
        $nodes = DB::table('divulgacion.chat_nodes')->whereIn('slug', ['deposito', 'documentos'])
            ->where('status', 'published')->where('needs_curator_review', false)->get()->keyBy('slug');
        if (! isset($nodes['deposito'], $nodes['documentos'])) return null;
        return ['texto' => $nodes['deposito']->answer."\n\nDocumentación: ".$nodes['documentos']->answer,
            'opciones' => [['label' => 'Abrir depósitos', 'url' => route('depositos.portal')]],
            'fuente' => 'conocimiento', 'intent' => 'deposito+documentos', 'secondary_intent' => 'documentos',
            'confianza' => 'MEDIUM', 'confianza_valor' => 0.65];
    }

    public function responder(string $pregunta, ?int $anterior = null, array $variantesRecientes = [], bool $registrar = true): ?array
    {
        $ranking = $this->ranker->evaluar($pregunta, $anterior);
        $confidence = $this->ranker->confianza($ranking);
        $best = $ranking['candidatos'][0] ?? null;
        if ($best === null || in_array($confidence['nivel'], ['UNKNOWN', 'LOW'], true) && ! $confidence['ambiguo']) {
            if ($registrar && $best !== null) {
                $this->registrarNoReconocida($pregunta, $best['score'], $best['id']);
                Log::info('portal_chat_unmatched', ['score' => $best['score'], 'duration_ms' => $ranking['duracion_ms']]);
            }
            return null;
        }
        $alternatives = array_map(static fn ($item) => ['id' => $item['id'], 'title' => $item['title'], 'score' => $item['score']], array_slice($ranking['candidatos'], 1, 3));
        if ($confidence['ambiguo']) {
            $choices = $ranking['clarification_candidates'] ?? [];
            if (count($choices) < 2) $choices = array_slice($ranking['candidatos'], 0, 2);
            $second = $choices[1];
            if ($registrar) {
                $this->registrarNoReconocida($pregunta, $best['score'], $best['id']);
            }
            return [
                'texto' => count($choices) > 2 ? '¿Sobre cuál de estos temas necesitas información?'
                    : '¿Te refieres a «'.$choices[0]['title'].'» o a «'.$second['title'].'»?',
                'opciones' => array_map(static fn ($item) =>
                    ['label' => $item['title'], 'pregunta' => $item['title']], array_slice($choices, 0, 3)),
                'confianza' => 'LOW', 'confianza_valor' => $confidence['valor'],
                'score' => $best['score'], 'alternativas' => $alternatives,
                'fuente' => 'aclaracion', 'intent' => 'ambiguous',
            ];
        }
        $node = $best['node'];
        $variantId = null;
        $answer = $node->answer;
        if ($confidence['nivel'] === 'HIGH') {
            [$answer, $variantId] = $this->elegirVariante((int) $node->id, $answer, $variantesRecientes);
        } else {
            $answer = 'Creo que preguntas por «'.$node->title.'». '.$answer;
        }
        if ($registrar) {
            DB::table('divulgacion.chat_nodes')->where('id', $node->id)->increment('uses');
            if ($variantId !== null) {
                DB::table('divulgacion.chat_variants')->where('id', $variantId)->increment('uses');
            }
            if ($anterior !== null && DB::table('divulgacion.chat_nodes')->where('id', $anterior)->exists()) {
                DB::table('divulgacion.chat_transitions')->insertOrIgnore(['from_id' => $anterior, 'to_id' => $node->id, 'uses' => 0]);
                DB::table('divulgacion.chat_transitions')->where('from_id', $anterior)->where('to_id', $node->id)->increment('uses');
            }
            Log::info('portal_chat_answer', ['intent' => $node->slug, 'score' => $best['score'],
                'confidence' => $confidence['nivel'], 'duration_ms' => $ranking['duracion_ms']]);
        }
        return ['texto' => $answer, 'opciones' => $this->opciones((string) $node->action, (int) $node->id),
            'node_id' => (int) $node->id, 'variant_id' => $variantId,
            'intent' => $best['intent'], 'node_slug' => $node->slug,
            'confianza' => $confidence['nivel'], 'confianza_valor' => $confidence['valor'],
            'score' => $best['score'], 'alternativas' => $alternatives, 'fuente' => 'conocimiento'];
    }

    /** Diagnóstico sin escribir uso, transiciones ni preguntas pendientes. Solo lo invoca curaduría. */
    public function diagnosticar(string $pregunta, ?int $anterior = null): array
    {
        $ranking = $this->ranker->evaluar($pregunta, $anterior);
        $confidence = $this->ranker->confianza($ranking);
        $candidates = array_map(static fn ($item) => [
            'id' => $item['id'], 'slug' => $item['slug'], 'intent' => $item['intent'],
            'title' => $item['title'], 'score' => $item['score'],
            'components' => $item['components'], 'profile' => $item['profile'],
        ], array_slice($ranking['candidatos'], 0, 5));
        return ['normal' => $ranking['normal'], 'tokens' => $ranking['tokens'],
            'sinonimos' => $ranking['sinonimos'], 'candidatos' => $candidates,
            'features' => $ranking['features'],
            'confianza' => $confidence, 'respuesta' => $ranking['candidatos'][0]['node']->answer ?? '',
            'duracion_ms' => $ranking['duracion_ms']];
    }

    public function valorar(int $nodeId, ?int $variantId, bool $helpful): void
    {
        $column = $helpful ? 'helpful' : 'unhelpful';
        DB::table('divulgacion.chat_nodes')->where('id', $nodeId)->increment($column);
        if ($variantId !== null) {
            DB::table('divulgacion.chat_variants')->where('id', $variantId)->where('node_id', $nodeId)->increment($column);
        }
    }

    private function registrarNoReconocida(string $pregunta, float $score, ?int $candidateId): void
    {
        $sample = preg_match('/\b(contrasena|contraseña|password|clave|token|secreto|secret|bearer|api[ -]?key)\b/iu', $pregunta)
            ? '[consulta sobre acceso o credenciales; texto omitido]'
            : Str::limit(trim(preg_replace('/[\w.+-]+@[\w.-]+\.[A-Za-z]{2,}|\b\+?\d[\d\s-]{7,}\b/u', '[dato omitido]', $pregunta) ?? ''), 240, '');
        if ($sample === '') {
            return;
        }
        $normal = $this->texto->normalizar($sample);
        $hash = hash('sha256', $normal);
        $groupKey = $hash;
        foreach (DB::table('divulgacion.chat_unmatched')->where('status', 'pending')->orderByDesc('occurrences')->limit(100)->get(['sample', 'group_key']) as $existing) {
            $comparison = $this->texto->normalizar($existing->sample);
            similar_text($normal, $comparison, $similarity);
            if ($similarity >= 78) {
                $groupKey = $existing->group_key ?: $hash;
                break;
            }
        }
        DB::table('divulgacion.chat_unmatched')->insertOrIgnore([
            'question_hash' => $hash, 'group_key' => $groupKey, 'sample' => $sample,
            'best_score' => $score, 'best_node_id' => $candidateId, 'occurrences' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('divulgacion.chat_unmatched')->where('question_hash', $hash)->increment('occurrences', 1, ['updated_at' => now()]);
    }

    /** @return array{string,?int} */
    private function elegirVariante(int $nodeId, string $default, array $recent): array
    {
        $variants = DB::table('divulgacion.chat_variants')->where('node_id', $nodeId)->where('active', true)->get();
        if ($variants->isEmpty()) {
            return [$default, null];
        }
        $fresh = $variants->filter(static fn ($variant) => ! in_array((int) $variant->id, $recent, true));
        $pool = $fresh->isNotEmpty() ? $fresh : $variants;
        $weighted = [];
        $total = 0;
        foreach ($pool as $variant) {
            $quality = ((int) $variant->helpful + 1) / ((int) $variant->helpful + (int) $variant->unhelpful + 2);
            $total += max(1, (int) round($variant->weight * (.5 + $quality) * 10));
            $weighted[] = [$variant, $total];
        }
        $draw = random_int(1, $total);
        foreach ($weighted as [$variant, $upper]) {
            if ($draw <= $upper) {
                return [$variant->text, (int) $variant->id];
            }
        }
        return [$default, null];
    }

    private function opciones(string $action, int $nodeId): array
    {
        $links = [
            'DEPOSIT_LINK' => ['Abrir depósitos', route('depositos.portal')],
            'MY_REQUESTS_LINK' => ['Mis solicitudes', auth()->check() ? route('depositos.mis-solicitudes') : route('login')],
            'CATALOG_LINK' => ['Abrir catálogo', route('portal.catalogo')],
            'LOGIN_LINK' => ['Iniciar sesión', route('login')],
            'CONTACT_LINK' => ['Escribir al laboratorio', 'mailto:adrian.troya@epn.edu.ec'],
        ];
        $options = isset($links[$action]) ? [['label' => $links[$action][0], 'url' => $links[$action][1]]] : [];
        $children = DB::table('divulgacion.chat_nodes')->where('parent_id', $nodeId)
            ->where('status', 'published')->where('needs_curator_review', false)
            ->orderBy('position')->limit(3)->get(['title']);
        foreach ($children as $child) {
            $options[] = ['label' => $child->title, 'pregunta' => $child->title];
        }
        return $options;
    }
}
