<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Presentation\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ConocimientoPortal;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\DetectorEntidadesChat;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ConsultaCatalogoPublico;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ConversacionBasica;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\CorpusChat;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\EvaluadorCorpusChat;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\TransferenciaConocimientoChat;

#[Layout('layouts.app', params: ['title' => 'Asistente del portal'])]
final class AdministrarAsistente extends Component
{
    use WithFileUploads;

    public ?int $nodeId = null;
    public ?int $parentId = null;
    public string $title = '';
    public string $answer = '';
    public string $aliasesText = '';
    public string $variantsText = '';
    public string $action = '';
    public string $status = 'draft';
    public bool $needsReview = false;
    public int $position = 0;
    public string $testQuestion = '';
    public array $testResult = [];
    public string $synonymTerm = '';
    public string $synonymCanonical = '';
    public array $unmatchedNode = [];
    public string $notice = '';
    public array $rankingWeights = [];
    public array $rankingThresholds = [];
    public ?int $testContextId = null;
    public string $testMode = 'published';
    public bool $showDebug = false;
    public array $confusion = [];
    public string $corpusSplit = 'calibration';
    public array $calibration = [];
    public string $rankingComment = '';
    public $importFile;
    public array $importPreview = [];

    public function mount(): void
    {
        $settings = DB::table('divulgacion.chat_settings')->where('key', 'ranking')->value('value');
        $ranking = $settings ? json_decode($settings, true) : config('chatbot.ranking');
        $this->rankingWeights = array_replace(config('chatbot.ranking.weights'), $ranking['weights'] ?? []);
        $this->rankingThresholds = array_replace(config('chatbot.ranking.thresholds'), $ranking['thresholds'] ?? []);
    }

    public function boot(): void
    {
        abort_unless(auth()->user()?->esCurador(), 403);
    }

    public function edit(int $id): void
    {
        $node = DB::table('divulgacion.chat_nodes')->find($id);
        abort_unless($node, 404);
        $this->nodeId = $id;
        $this->parentId = $node->parent_id;
        $this->title = $node->title;
        $this->answer = $node->answer;
        $this->action = $node->action ?? '';
        $this->status = $node->status;
        $this->needsReview = (bool) $node->needs_curator_review;
        $this->position = (int) $node->position;
        $this->aliasesText = DB::table('divulgacion.chat_aliases')->where('node_id', $id)->pluck('phrase')->implode("\n");
        $this->variantsText = DB::table('divulgacion.chat_variants')->where('node_id', $id)
            ->get()->map(fn ($variant) => $variant->weight.'|'.($variant->active ? 'activo' : 'inactivo').'|'.$variant->text)->implode("\n");
        $draft = DB::table('divulgacion.chat_node_drafts')->where('node_id', $id)->value('payload');
        if ($draft !== null) {
            $values = json_decode($draft, true, flags: JSON_THROW_ON_ERROR);
            $this->parentId = $values['parent_id'];
            $this->title = $values['title'];
            $this->answer = $values['answer'];
            $this->action = $values['action'] ?? '';
            $this->needsReview = $values['needs_curator_review'];
            $this->position = $values['position'];
            $this->aliasesText = implode("\n", $values['aliases']);
            $this->variantsText = implode("\n", array_map(static fn ($v) => $v['weight'].'|'.($v['active'] ? 'activo' : 'inactivo').'|'.$v['text'], $values['variants']));
        }
        $this->notice = '';
    }

    public function newNode(): void
    {
        $this->reset('nodeId', 'parentId', 'title', 'answer', 'aliasesText', 'variantsText', 'action', 'needsReview', 'position', 'notice');
        $this->status = 'draft';
    }

    public function createChild(int $parentId): void
    {
        abort_unless(DB::table('divulgacion.chat_nodes')->where('id', $parentId)->exists(), 404);
        $this->newNode();
        $this->parentId = $parentId;
    }

    public function save(): void
    {
        $data = $this->validate([
            'parentId' => 'nullable|integer|exists:divulgacion.chat_nodes,id',
            'title' => 'required|string|max:160',
            'answer' => 'required|string|max:3000',
            'aliasesText' => 'nullable|string|max:4000',
            'variantsText' => 'nullable|string|max:5000',
            'action' => 'nullable|in:,DEPOSIT_LINK,MY_REQUESTS_LINK,CATALOG_LINK,LOGIN_LINK,CONTACT_LINK',
            'needsReview' => 'boolean',
            'position' => 'integer|min:0|max:999',
        ]);
        if ($this->nodeId !== null && $this->wouldCycle($this->nodeId, $this->parentId)) {
            $this->addError('parentId', 'El nodo padre no puede ser el propio nodo ni uno de sus descendientes.');
            return;
        }
        $aliases = array_values(array_unique(array_filter(array_map('trim', preg_split('/\R/u', $this->aliasesText) ?: []))));
        $variants = [];
        foreach (array_filter(array_map('trim', preg_split('/\R/u', $this->variantsText) ?: [])) as $line) {
            $parts = explode('|', $line, 3);
            if (count($parts) === 3 && in_array(mb_strtolower(trim($parts[1])), ['activo', 'inactivo'], true)) {
                [$weight, $state, $text] = $parts;
                $active = mb_strtolower(trim($state)) === 'activo';
            } else {
                [$weight, $text] = str_contains($line, '|') ? explode('|', $line, 2) : ['1', $line];
                $active = true;
            }
            $text = trim($text);
            if ($text !== '') {
                $variants[] = ['weight' => max(1, min(10, (int) $weight)), 'active' => $active, 'text' => $text];
            }
        }
        DB::transaction(function () use ($data, $aliases, $variants): void {
            $values = [
                'parent_id' => $data['parentId'], 'title' => trim($data['title']),
                'answer' => trim($data['answer']), 'action' => $data['action'] ?: null,
                'needs_curator_review' => $data['needsReview'], 'position' => $data['position'],
                'aliases' => array_map(static fn ($phrase) => Str::limit($phrase, 240, ''), $aliases),
                'variants' => $variants,
            ];
            if ($this->nodeId === null) {
                $slug = Str::slug($data['title'], '_');
                $this->nodeId = DB::table('divulgacion.chat_nodes')->insertGetId([
                    'slug' => substr($slug ?: 'nodo', 0, 80).'_'.Str::lower(Str::random(6)),
                    'parent_id' => $values['parent_id'], 'title' => $values['title'], 'answer' => $values['answer'],
                    'action' => $values['action'], 'status' => 'draft', 'needs_curator_review' => $values['needs_curator_review'],
                    'position' => $values['position'], 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $payload = json_encode($values, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            DB::table('divulgacion.chat_node_drafts')->updateOrInsert(['node_id' => $this->nodeId],
                ['payload' => $payload, 'edited_by' => auth()->id(), 'updated_at' => now()]);
            $version = (int) DB::table('divulgacion.chat_nodes')->where('id', $this->nodeId)->value('version');
            DB::table('divulgacion.chat_node_revisions')->insert(['node_id' => $this->nodeId,
                'version' => $version + 1, 'event' => 'draft_saved', 'actor_id' => auth()->id(),
                'payload' => $payload, 'created_at' => now()]);
        });
        $this->notice = 'Borrador guardado. El chat público sigue usando la versión publicada.';
    }

    public function deactivate(int $id): void
    {
        $node = DB::table('divulgacion.chat_nodes')->find($id);
        abort_unless($node, 404);
        DB::transaction(function () use ($id, $node): void {
            DB::table('divulgacion.chat_nodes')->where('id', $id)->update(['status' => 'inactive', 'updated_at' => now()]);
            DB::table('divulgacion.chat_node_revisions')->insert(['node_id' => $id, 'version' => $node->version,
                'event' => 'deactivated', 'actor_id' => auth()->id(), 'payload' => json_encode(['status' => 'inactive']), 'created_at' => now()]);
        });
        $this->notice = 'Nodo desactivado.';
    }

    public function togglePublished(int $id): void
    {
        $node = DB::table('divulgacion.chat_nodes')->find($id);
        abort_unless($node, 404);
        if (DB::table('divulgacion.chat_node_drafts')->where('node_id', $id)->exists()) {
            $this->publishDraft($id);
            return;
        }
        if ($node->status === 'published') {
            DB::table('divulgacion.chat_nodes')->where('id', $id)->update(['status' => 'draft', 'updated_at' => now()]);
            DB::table('divulgacion.chat_node_revisions')->insert(['node_id' => $id, 'version' => $node->version,
                'event' => 'unpublished', 'actor_id' => auth()->id(), 'payload' => json_encode(['status' => 'draft']), 'created_at' => now()]);
            $this->notice = 'Respuesta retirada del chat.';
            return;
        }
        $this->notice = 'Edita y guarda un borrador antes de publicar.';
    }

    public function publishDraft(int $id): void
    {
        $draft = DB::table('divulgacion.chat_node_drafts')->where('node_id', $id)->first();
        abort_unless($draft, 404);
        $data = json_decode($draft->payload, true, flags: JSON_THROW_ON_ERROR);
        if (($data['needs_curator_review'] ?? true) || $this->wouldCycle($id, $data['parent_id'] ?? null)) {
            $this->notice = 'Revisa el contenido pendiente y la posición del nodo antes de publicarlo.';
            return;
        }
        DB::transaction(function () use ($id, $data, $draft): void {
            $node = DB::table('divulgacion.chat_nodes')->where('id', $id)->lockForUpdate()->first();
            abort_unless($node, 404);
            DB::table('divulgacion.chat_nodes')->where('id', $id)->update([
                'parent_id' => $data['parent_id'], 'title' => $data['title'], 'answer' => $data['answer'],
                'action' => $data['action'], 'position' => $data['position'], 'status' => 'published',
                'needs_curator_review' => false, 'version' => $node->version + 1,
                'published_at' => now(), 'published_by' => auth()->id(), 'updated_at' => now(),
            ]);
            DB::table('divulgacion.chat_aliases')->where('node_id', $id)->delete();
            foreach ($data['aliases'] as $phrase) {
                DB::table('divulgacion.chat_aliases')->insertOrIgnore(['node_id' => $id, 'phrase' => $phrase]);
            }
            $existing = DB::table('divulgacion.chat_variants')->where('node_id', $id)->get()->keyBy('text');
            DB::table('divulgacion.chat_variants')->where('node_id', $id)->whereNotIn('text', array_column($data['variants'], 'text'))->delete();
            foreach ($data['variants'] as $variant) {
                if ($saved = $existing->get($variant['text'])) {
                    DB::table('divulgacion.chat_variants')->where('id', $saved->id)->update(['weight' => $variant['weight'], 'active' => $variant['active']]);
                } else {
                    DB::table('divulgacion.chat_variants')->insert(['node_id' => $id, ...$variant]);
                }
            }
            DB::table('divulgacion.chat_node_revisions')->insert(['node_id' => $id, 'version' => $node->version + 1,
                'event' => 'published', 'actor_id' => auth()->id(), 'payload' => $draft->payload, 'created_at' => now()]);
            DB::table('divulgacion.chat_node_drafts')->where('node_id', $id)->delete();
        });
        $this->notice = 'Borrador publicado.';
    }

    public function move(int $id, int $delta): void
    {
        if (! in_array($delta, [-1, 1], true)) {
            return;
        }
        $node = DB::table('divulgacion.chat_nodes')->find($id);
        if (! $node) {
            return;
        }
        $siblings = DB::table('divulgacion.chat_nodes')->where('parent_id', $node->parent_id)
            ->orderBy('position')->orderBy('id')->pluck('id')->all();
        $siblings = array_map('intval', $siblings);
        $index = array_search($id, $siblings, true);
        if ($index === false || ! isset($siblings[$index + $delta])) {
            return;
        }
        [$siblings[$index], $siblings[$index + $delta]] = [$siblings[$index + $delta], $siblings[$index]];
        DB::transaction(function () use ($siblings): void {
            foreach ($siblings as $position => $siblingId) {
                DB::table('divulgacion.chat_nodes')->where('id', $siblingId)->update(['position' => $position]);
            }
        });
    }

    public function addSynonym(): void
    {
        $this->validate(['synonymTerm' => 'required|string|max:80', 'synonymCanonical' => 'required|string|max:80']);
        DB::table('divulgacion.chat_synonyms')->updateOrInsert(
            ['term' => Str::lower(Str::ascii(trim($this->synonymTerm)))],
            ['canonical' => Str::lower(Str::ascii(trim($this->synonymCanonical)))],
        );
        $this->reset('synonymTerm', 'synonymCanonical');
    }

    public function removeSynonym(int $id): void
    {
        DB::table('divulgacion.chat_synonyms')->where('id', $id)->delete();
    }

    public function associate(int $unmatchedId): void
    {
        $nodeId = (int) ($this->unmatchedNode[$unmatchedId] ?? 0);
        $question = DB::table('divulgacion.chat_unmatched')->find($unmatchedId);
        $node = DB::table('divulgacion.chat_nodes')->find($nodeId);
        if (! $question || ! $node) {
            return;
        }
        $group = $question->group_key ?: $question->question_hash;
        DB::transaction(function () use ($group, $nodeId): void {
            $questions = DB::table('divulgacion.chat_unmatched')->where('group_key', $group)->where('status', 'pending')->get();
            foreach ($questions as $item) {
                DB::table('divulgacion.chat_aliases')->insertOrIgnore(['node_id' => $nodeId, 'phrase' => Str::limit($item->sample, 240, '')]);
            }
            DB::table('divulgacion.chat_unmatched')->where('group_key', $group)->update(['status' => 'resolved', 'updated_at' => now()]);
        });
        $this->notice = 'Grupo asociado al nodo «'.$node->title.'».';
    }

    public function markUnmatched(int $id, string $status): void
    {
        abort_unless(in_array($status, ['ignored', 'resolved'], true), 422);
        $question = DB::table('divulgacion.chat_unmatched')->find($id);
        abort_unless($question, 404);
        DB::table('divulgacion.chat_unmatched')->where('group_key', $question->group_key ?: $question->question_hash)
            ->update(['status' => $status, 'updated_at' => now()]);
        $this->notice = $status === 'ignored' ? 'Grupo ignorado.' : 'Grupo marcado como resuelto.';
    }

    public function createFromUnmatched(int $id): void
    {
        $question = DB::table('divulgacion.chat_unmatched')->find($id);
        abort_unless($question, 404);
        $this->newNode();
        $this->title = Str::limit($question->sample, 160, '');
        $this->aliasesText = DB::table('divulgacion.chat_unmatched')
            ->where('group_key', $question->group_key ?: $question->question_hash)->pluck('sample')->implode("\n");
        $this->needsReview = true;
        $this->notice = 'Completa y verifica la respuesta institucional antes de publicar este nodo.';
    }

    public function saveRanking(): void
    {
        $this->validate([
            'rankingWeights.*' => 'required|numeric|between:0,1',
            'rankingThresholds.*' => 'required|numeric|between:0,1',
            'rankingComment' => 'nullable|string|max:240',
        ]);
        $defaults = config('chatbot.ranking');
        $weights = array_intersect_key($this->rankingWeights, $defaults['weights']);
        if (count($weights) !== count($defaults['weights']) || array_sum($weights) <= 0) {
            $this->addError('rankingWeights', 'Todos los pesos deben estar presentes y su suma debe ser positiva.');
            return;
        }
        $sum = array_sum($weights);
        $weights = array_map(static fn ($weight) => round((float) $weight / $sum, 4), $weights);
        $thresholds = array_intersect_key($this->rankingThresholds, $defaults['thresholds']);
        if (count($thresholds) !== count($defaults['thresholds'])
            || $thresholds['high'] <= $thresholds['medium']
            || $thresholds['medium'] <= $thresholds['unknown']
            || $thresholds['ambiguous_min'] < $thresholds['unknown']) {
            $this->addError('rankingThresholds', 'Ordena los umbrales de confianza y mantén el mínimo de ambigüedad sobre UNKNOWN.');
            return;
        }
        $settings = json_encode(['weights' => $weights, 'thresholds' => $thresholds,
            'confidence_formula' => $defaults['confidence_formula']], JSON_THROW_ON_ERROR);
        DB::transaction(function () use ($settings): void {
            DB::table('divulgacion.chat_settings')->updateOrInsert(['key' => 'ranking'], [
                'value' => $settings, 'updated_at' => now(),
            ]);
            DB::table('divulgacion.chat_ranking_versions')->insert([
                'settings' => $settings, 'actor_id' => auth()->id(),
                'comment' => trim($this->rankingComment) ?: null, 'created_at' => now(),
            ]);
        });
        $this->rankingWeights = $weights;
        $this->rankingComment = '';
        $this->notice = 'Pesos normalizados y guardados. El siguiente mensaje usará estos valores.';
    }

    public function restoreRanking(int $versionId): void
    {
        $version = DB::table('divulgacion.chat_ranking_versions')->find($versionId);
        abort_unless($version, 404);
        $settings = json_decode($version->settings, true, flags: JSON_THROW_ON_ERROR);
        abort_unless(isset($settings['weights'], $settings['thresholds']), 422);
        DB::transaction(function () use ($version): void {
            DB::table('divulgacion.chat_settings')->updateOrInsert(['key' => 'ranking'], [
                'value' => $version->settings, 'updated_at' => now(),
            ]);
            DB::table('divulgacion.chat_ranking_versions')->insert([
                'settings' => $version->settings, 'actor_id' => auth()->id(),
                'comment' => 'Restauración de versión '.$version->id, 'created_at' => now(),
            ]);
        });
        $this->rankingWeights = $settings['weights'];
        $this->rankingThresholds = $settings['thresholds'];
        $this->notice = 'Configuración de ranking restaurada.';
    }

    public function test(ConocimientoPortal $knowledge, DetectorEntidadesChat $detector, ConsultaCatalogoPublico $catalog, ConversacionBasica $social): void
    {
        $this->validate(['testQuestion' => 'required|string|max:500']);
        $diagnosis = $knowledge->diagnosticar($this->testQuestion, $this->testContextId);
        $entities = $detector->extraer($this->testQuestion);
        $socialAnswer = $social->responder($this->testQuestion);
        $catalogAnswer = $socialAnswer === null ? $catalog->responder($this->testQuestion) : null;
        $selected = $diagnosis['candidatos'][0] ?? null;
        $knowledgeAnswer = $socialAnswer === null && $catalogAnswer === null
            ? $knowledge->responder($this->testQuestion, $this->testContextId, registrar: false) : null;
        $actual = $socialAnswer ?? $catalogAnswer ?? $knowledgeAnswer;
        if ($this->testMode === 'draft' && $this->nodeId !== null) {
            $payload = DB::table('divulgacion.chat_node_drafts')->where('node_id', $this->nodeId)->value('payload');
            if ($payload !== null) {
                $draft = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
                $actual = ['texto' => $draft['answer'], 'intent' => 'borrador:'.$this->nodeId,
                    'fuente' => 'borrador', 'confianza' => 'PREVIEW', 'confianza_valor' => 0.0];
            }
        }
        $this->testResult = [
            'node' => $actual['intent'] ?? 'UNKNOWN',
            'score' => $selected['score'] ?? 0,
            'confidence' => $actual['confianza'] ?? $diagnosis['confianza']['nivel'],
            'confidence_value' => $actual['confianza_valor'] ?? $diagnosis['confianza']['valor'],
            'margin' => $diagnosis['confianza']['margen'],
            'ambiguous' => $diagnosis['confianza']['ambiguo'],
            'answer' => $actual['texto'] ?? 'El chat ofrecerá temas cercanos para aclarar la pregunta.',
            'normal' => $diagnosis['normal'], 'tokens' => $diagnosis['tokens'],
            'synonyms' => $diagnosis['sinonimos'], 'entities' => $entities,
            'candidates' => $diagnosis['candidatos'], 'duration_ms' => $diagnosis['duracion_ms'],
            'action' => $actual['intent'] ?? 'UNKNOWN',
            'source' => $actual['fuente'] ?? 'unknown',
            'domain' => $diagnosis['features']['domain'] ?? null,
            'topic' => $selected['profile']['topic'] ?? null,
            'node_slug' => $selected['slug'] ?? null,
            'negated_domain' => $diagnosis['features']['negated_domain'] ?? null,
            'suggestions' => $actual['opciones'] ?? [],
        ];
    }

    public function evaluateCorpus(EvaluadorCorpusChat $evaluator): void
    {
        abort_unless(in_array($this->corpusSplit, ['train', 'calibration', 'test'], true), 422);
        $this->confusion = $evaluator->evaluar($this->corpusSplit);
    }

    public function calibrate(EvaluadorCorpusChat $evaluator): void
    {
        $this->calibration = $evaluator->calibrar();
        $this->notice = 'Calibración calculada con CALIBRATION. Los ajustes publicados no cambiaron.';
    }

    public function useSuggested(): void
    {
        if ($this->calibration === []) return;
        $this->rankingWeights = $this->calibration['suggested_weights'];
        $this->rankingThresholds = $this->calibration['suggested_thresholds'];
        $this->notice = 'Sugerencia copiada al formulario. Revisa y guarda para publicarla.';
    }

    public function downloadKnowledge(TransferenciaConocimientoChat $transfer)
    {
        $json = $transfer->exportar();
        return response()->streamDownload(static function () use ($json): void { echo $json; },
            'hubdigital-conocimiento-'.now()->format('Ymd-His').'.json', ['Content-Type' => 'application/json; charset=UTF-8']);
    }

    public function previewImport(TransferenciaConocimientoChat $transfer): void
    {
        $this->validate(['importFile' => 'required|file|mimes:json,txt|max:2048']);
        try {
            $this->importPreview = $transfer->previsualizar($this->importFile->get());
        } catch (\InvalidArgumentException|\JsonException $error) {
            $this->importPreview = [];
            $this->addError('importFile', $error->getMessage());
        }
    }

    public function importKnowledge(TransferenciaConocimientoChat $transfer): void
    {
        $this->validate(['importFile' => 'required|file|mimes:json,txt|max:2048']);
        $json = $this->importFile->get();
        abort_unless($this->importPreview !== [] && hash_equals($this->importPreview['hash'], hash('sha256', $json)), 422);
        try {
            $result = $transfer->importar($json, (string) auth()->id());
            $this->importPreview = [];
            $this->reset('importFile');
            $this->notice = 'Importados '.$result['nodes'].' nodos como borradores; revisa y publica cada uno. Sinónimos y ranking aplicados.';
        } catch (\InvalidArgumentException|\JsonException $error) {
            $this->addError('importFile', $error->getMessage());
        }
    }

    private function wouldCycle(int $id, ?int $parent): bool
    {
        for ($depth = 0; $parent !== null && $depth < 30; $depth++) {
            if ($parent === $id) {
                return true;
            }
            $parent = DB::table('divulgacion.chat_nodes')->where('id', $parent)->value('parent_id');
        }
        return $parent !== null;
    }

    public function render(): View
    {
        $nodes = DB::table('divulgacion.chat_nodes')->orderBy('position')->orderBy('id')->get();
        $draftIds = DB::table('divulgacion.chat_node_drafts')->pluck('node_id')->map(fn ($id) => (int) $id)->all();
        foreach ($nodes as $node) $node->has_draft = in_array((int) $node->id, $draftIds, true);
        $ordered = collect();
        $parentIds = $nodes->pluck('parent_id')->filter()->map(fn ($id) => (int) $id)->all();
        $visit = function (?int $parentId, int $depth, array $ancestors) use (&$visit, $nodes, $ordered, $parentIds): void {
            if ($depth > 20) {
                return;
            }
            foreach ($nodes as $node) {
                $actualParent = $node->parent_id === null ? null : (int) $node->parent_id;
                if ($actualParent !== $parentId || $ordered->contains('id', $node->id)) {
                    continue;
                }
                $node->depth = $depth;
                $node->ancestor_ids = $ancestors;
                $node->has_children = in_array((int) $node->id, $parentIds, true);
                $ordered->push($node);
                $visit((int) $node->id, $depth + 1, [...$ancestors, (int) $node->id]);
            }
        };
        $visit(null, 0, []);
        foreach ($nodes as $node) {
            if (! $ordered->contains('id', $node->id)) {
                $node->depth = 0;
                $node->ancestor_ids = [];
                $node->has_children = in_array((int) $node->id, $parentIds, true);
                $ordered->push($node);
            }
        }
        $pending = DB::table('divulgacion.chat_unmatched')->where('status', 'pending')
            ->orderByDesc('occurrences')->limit(200)->get();
        $groups = $pending->groupBy(fn ($item) => $item->group_key ?: $item->question_hash)
            ->map(function ($rows) use ($nodes) {
                $representative = $rows->sortByDesc('occurrences')->first();
                return (object) [
                    'id' => $representative->id, 'sample' => $representative->sample,
                    'occurrences' => (int) $rows->sum('occurrences'),
                    'variants' => $rows->pluck('sample')->unique()->take(5)->all(),
                    'best_score' => (float) $representative->best_score,
                    'candidate' => $nodes->firstWhere('id', $representative->best_node_id)?->title,
                ];
            })->sortByDesc('occurrences')->take(20)->values();
        $metrics = DB::table('divulgacion.chat_metrics_daily')->selectRaw(
            'COALESCE(sum(conversations),0) conversations, COALESCE(sum(messages),0) messages, COALESCE(sum(resolved),0) resolved, COALESCE(sum(unknown),0) unknown, COALESCE(sum(catalog_queries),0) catalog_queries, COALESCE(sum(feedback_positive),0) feedback_positive, COALESCE(sum(feedback_negative),0) feedback_negative, COALESCE(sum(confidence_sum),0) confidence_sum, COALESCE(sum(confidence_observations),0) confidence_observations, COALESCE(sum(latency_ms_sum),0) latency_ms_sum, COALESCE(sum(high),0) high, COALESCE(sum(medium),0) medium, COALESCE(sum(low),0) low, COALESCE(sum(clarifications),0) clarifications, COALESCE(sum(clarifications_resolved),0) clarifications_resolved, COALESCE(sum(reformulated),0) reformulated'
        )->first();
        return view('catalogopublico::livewire.administrar-asistente', [
            'nodes' => $ordered,
            'synonyms' => DB::table('divulgacion.chat_synonyms')->orderBy('term')->get(),
            'unmatched' => $groups,
            'metrics' => $metrics,
            'topIntents' => $nodes->sortByDesc('uses')->take(5),
            'totalQuestions' => (int) $nodes->sum('uses'),
            'totalFeedbackNegative' => (int) $nodes->sum('unhelpful'),
            'corpusSizes' => CorpusChat::tamanos(),
            'rankingVersions' => DB::table('divulgacion.chat_ranking_versions')->orderByDesc('id')->limit(10)->get(),
        ]);
    }
}
