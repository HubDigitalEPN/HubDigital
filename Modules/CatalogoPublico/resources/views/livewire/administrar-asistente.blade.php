<div class="mx-auto w-full max-w-7xl space-y-5 p-4 sm:p-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="font-display text-2xl font-bold text-blue-navy">Asistente del portal</h1>
            <p class="text-sm text-text-secondary">Edita preguntas, respuestas y sugerencias publicadas. Los borradores no aparecen en el chat.</p>
        </div>
        <button type="button" wire:click="newNode" class="rounded-lg bg-blue-navy px-4 py-2 text-sm font-semibold text-white hover:bg-[#244872]">Nuevo nodo</button>
    </div>

    @if($notice)
        <div role="status" class="rounded-lg border border-science-blue/25 bg-science-blue/5 px-3 py-2 text-sm text-blue-navy">{{ $notice }}</div>
    @endif

    <section class="rounded-xl border border-border bg-white p-4 shadow-sm" aria-label="Analítica del asistente">
        <h2 class="font-display text-lg font-semibold text-blue-navy">Uso del asistente</h2>
        <div class="mt-3 grid grid-cols-2 gap-3 text-sm sm:grid-cols-3 lg:grid-cols-5">
            <span>Conversaciones <strong class="block text-lg text-blue-navy">{{ $metrics->conversations }}</strong></span>
            <span>Mensajes <strong class="block text-lg text-blue-navy">{{ $metrics->messages }}</strong></span>
            <span>Resueltas <strong class="block text-lg text-blue-navy">{{ $metrics->resolved }}</strong></span>
            <span>UNKNOWN <strong class="block text-lg text-blue-navy">{{ $metrics->unknown }} ({{ $metrics->messages ? round(100 * $metrics->unknown / $metrics->messages, 1) : 0 }} %)</strong></span>
            <span>Catálogo <strong class="block text-lg text-blue-navy">{{ $metrics->catalog_queries }}</strong></span>
            <span>Confianza media <strong class="block text-lg text-blue-navy">{{ $metrics->confidence_observations ? round(100 * $metrics->confidence_sum / $metrics->confidence_observations, 1) : 0 }} %</strong></span>
            <span>Tiempo medio <strong class="block text-lg text-blue-navy">{{ $metrics->messages ? round($metrics->latency_ms_sum / $metrics->messages) : 0 }} ms</strong></span>
            <span>Útil / no útil <strong class="block text-lg text-blue-navy">{{ $metrics->feedback_positive }} / {{ $metrics->feedback_negative }}</strong></span>
            <span>HIGH / MEDIUM / LOW <strong class="block text-lg text-blue-navy">{{ $metrics->messages ? round(100*$metrics->high/$metrics->messages,1) : 0 }} / {{ $metrics->messages ? round(100*$metrics->medium/$metrics->messages,1) : 0 }} / {{ $metrics->messages ? round(100*$metrics->low/$metrics->messages,1) : 0 }} %</strong></span>
            <span>UNKNOWN / aclaraciones <strong class="block text-lg text-blue-navy">{{ $metrics->messages ? round(100*$metrics->unknown/$metrics->messages,1) : 0 }} / {{ $metrics->messages ? round(100*$metrics->clarifications/$metrics->messages,1) : 0 }} %</strong></span>
            <span>Aclaraciones resueltas / reformuladas <strong class="block text-lg text-blue-navy">{{ $metrics->clarifications ? round(100*$metrics->clarifications_resolved/$metrics->clarifications,1) : 0 }} / {{ $metrics->messages ? round(100*$metrics->reformulated/$metrics->messages,1) : 0 }} %</strong></span>
            <span>Feedback positivo / negativo <strong class="block text-lg text-blue-navy">{{ ($metrics->feedback_positive+$metrics->feedback_negative) ? round(100*$metrics->feedback_positive/($metrics->feedback_positive+$metrics->feedback_negative),1) : 0 }} / {{ ($metrics->feedback_positive+$metrics->feedback_negative) ? round(100*$metrics->feedback_negative/($metrics->feedback_positive+$metrics->feedback_negative),1) : 0 }} %</strong></span>
            <span class="sm:col-span-2">Más usadas <strong class="block text-blue-navy">{{ $topIntents->filter(fn($node) => $node->uses > 0)->map(fn($node) => $node->title.' ('.$node->uses.')')->implode(', ') ?: 'Sin datos' }}</strong></span>
        </div>
    </section>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(19rem,.9fr)]">
        <section class="rounded-xl border border-border bg-white p-4 shadow-sm" aria-label="Árbol de conocimiento" x-data="{ collapsed: {} }">
            <div class="mb-3 flex flex-wrap gap-4 text-sm text-text-secondary">
                <span><strong class="text-blue-navy">{{ $totalQuestions }}</strong> respuestas usadas</span>
                <span><strong class="text-blue-navy">{{ $unmatched->count() }}</strong> preguntas por revisar</span>
                <span><strong class="text-blue-navy">{{ $totalFeedbackNegative }}</strong> opiniones negativas</span>
            </div>
            <h2 class="mb-3 font-display text-lg font-semibold text-blue-navy">Árbol de respuestas</h2>
            <div class="max-h-[35rem] space-y-1 overflow-y-auto">
                @foreach($nodes as $node)
                    <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-border px-2 py-1.5" style="margin-left: {{ min(8, $node->depth) * 0.7 }}rem" x-show="!@js($node->ancestor_ids).some(id => collapsed[id])">
                        <div class="min-w-0">
                            @if($node->has_children)
                                <button type="button" @click="collapsed[{{ $node->id }}] = !collapsed[{{ $node->id }}]" :aria-expanded="!collapsed[{{ $node->id }}]" aria-label="Expandir o contraer {{ $node->title }}" class="mr-1 rounded px-1 text-science-blue hover:bg-science-blue/10" x-text="collapsed[{{ $node->id }}] ? '▸' : '▾'"></button>
                            @endif
                            <span class="text-sm font-semibold text-blue-navy">{{ $node->title }}</span>
                            <span class="ml-2 text-xs {{ $node->status === 'published' && !$node->needs_curator_review ? 'text-science-blue' : 'text-text-secondary' }}">{{ $node->needs_curator_review ? 'Revisión pendiente' : ($node->status === 'published' ? 'Publicado' : ($node->status === 'draft' ? 'Borrador' : 'Inactivo')) }}</span>
                            @if($node->has_draft)<span class="ml-1 text-xs font-semibold text-amber-700">· borrador sin publicar</span>@endif
                        </div>
                        <div class="flex shrink-0 items-center gap-1 text-xs">
                            <button type="button" wire:click="move({{ $node->id }}, -1)" aria-label="Subir {{ $node->title }}" class="rounded px-1.5 py-1 text-science-blue hover:bg-science-blue/10">↑</button>
                            <button type="button" wire:click="move({{ $node->id }}, 1)" aria-label="Bajar {{ $node->title }}" class="rounded px-1.5 py-1 text-science-blue hover:bg-science-blue/10">↓</button>
                            <button type="button" wire:click="createChild({{ $node->id }})" class="rounded px-2 py-1 text-science-blue hover:bg-science-blue/10">Crear hijo</button>
                            <button type="button" wire:click="edit({{ $node->id }})" class="rounded px-2 py-1 font-semibold text-science-blue hover:bg-science-blue/10">Editar</button>
                            <button type="button" wire:click="togglePublished({{ $node->id }})" wire:confirm="¿Aplicar este cambio de publicación?" class="rounded px-2 py-1 text-science-blue hover:bg-science-blue/10">{{ $node->has_draft ? 'Publicar borrador' : ($node->status === 'published' ? 'Despublicar' : 'Publicar') }}</button>
                            @if($node->status !== 'inactive')
                                <button type="button" wire:click="deactivate({{ $node->id }})" wire:confirm="¿Desactivar esta respuesta?" class="rounded px-2 py-1 text-red-700 hover:bg-red-50">Desactivar</button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="rounded-xl border border-border bg-white p-4 shadow-sm" aria-label="Editor de respuestas">
            <h2 class="mb-3 font-display text-lg font-semibold text-blue-navy">{{ $nodeId ? 'Editar respuesta' : 'Nueva respuesta' }}</h2>
            <form wire:submit="save" class="space-y-3 text-sm">
                <label class="block font-medium text-blue-navy">Título<input type="text" wire:model="title" maxlength="160" class="mt-1 w-full rounded-lg border border-border px-3 py-2" required /></label>
                @error('title') <p class="text-xs text-red-700">{{ $message }}</p> @enderror
                <label class="block font-medium text-blue-navy">Nodo padre<select wire:model="parentId" class="mt-1 w-full rounded-lg border border-border px-3 py-2"><option value="">Raíz</option>@foreach($nodes as $candidate) @if($candidate->id !== $nodeId)<option value="{{ $candidate->id }}">{{ $candidate->title }}</option>@endif @endforeach</select></label>
                @error('parentId') <p class="text-xs text-red-700">{{ $message }}</p> @enderror
                <label class="block font-medium text-blue-navy">Respuesta institucional<textarea wire:model="answer" rows="4" maxlength="3000" class="mt-1 w-full rounded-lg border border-border px-3 py-2" required></textarea></label>
                @error('answer') <p class="text-xs text-red-700">{{ $message }}</p> @enderror
                <label class="block font-medium text-blue-navy">Preguntas equivalentes <span class="font-normal text-text-secondary">(una por línea)</span><textarea wire:model="aliasesText" rows="3" class="mt-1 w-full rounded-lg border border-border px-3 py-2"></textarea></label>
                <label class="block font-medium text-blue-navy">Variantes de respuesta <span class="font-normal text-text-secondary">(peso|activo|texto o peso|inactivo|texto, una por línea)</span><textarea wire:model="variantsText" rows="3" class="mt-1 w-full rounded-lg border border-border px-3 py-2"></textarea></label>
                <div class="grid grid-cols-2 gap-3">
                    <p class="text-xs text-text-secondary">Guardar crea un borrador. Publica desde el árbol después de probarlo.</p>
                    <label class="block font-medium text-blue-navy">Enlace<select wire:model="action" class="mt-1 w-full rounded-lg border border-border px-3 py-2"><option value="">Ninguno</option><option value="DEPOSIT_LINK">Depósitos</option><option value="MY_REQUESTS_LINK">Mis solicitudes</option><option value="CATALOG_LINK">Catálogo</option><option value="LOGIN_LINK">Iniciar sesión</option><option value="CONTACT_LINK">Contacto</option></select></label>
                </div>
                <label class="flex items-center gap-2 text-blue-navy"><input type="checkbox" wire:model="needsReview" /> Requiere revisión curatorial antes de publicarse</label>
                <button type="submit" class="rounded-lg bg-blue-navy px-4 py-2 font-semibold text-white hover:bg-[#244872]">Guardar borrador</button>
            </form>
        </section>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <section class="rounded-xl border border-border bg-white p-4 shadow-sm">
            <h2 class="font-display text-lg font-semibold text-blue-navy">Probador</h2>
            <form wire:submit="test" class="mt-3 flex flex-wrap gap-2">
                <input type="text" wire:model="testQuestion" maxlength="500" placeholder="Escribe una pregunta real" class="min-w-0 flex-1 rounded-lg border border-border px-3 py-2 text-sm" />
                <select wire:model="testContextId" aria-label="Intención anterior para la prueba" class="rounded-lg border border-border px-2 py-2 text-sm"><option value="">Sin contexto</option>@foreach($nodes as $node)<option value="{{ $node->id }}">Después de: {{ $node->title }}</option>@endforeach</select>
                <select wire:model="testMode" aria-label="Versión para probar" class="rounded-lg border border-border px-2 py-2 text-sm"><option value="published">Probar publicado</option><option value="draft">Probar borrador seleccionado</option></select>
                <button type="submit" class="rounded-lg bg-blue-navy px-4 py-2 text-sm font-semibold text-white">Probar</button>
            </form>
            @if($testResult)
                <div class="mt-3 rounded-lg bg-[#F5F8FC] p-3 text-sm text-blue-navy">
                    <p><strong>Intención:</strong> {{ $testResult['node'] }} · <strong>Confianza:</strong> {{ $testResult['confidence'] }} ({{ round(100 * $testResult['confidence_value'], 1) }} %) · <strong>Score:</strong> {{ $testResult['score'] }} · <strong>Margen:</strong> {{ $testResult['margin'] }}</p>
                    <p class="mt-1 text-xs"><strong>Dominio:</strong> {{ $testResult['domain'] ?? 'sin evidencia' }} · <strong>Tema:</strong> {{ $testResult['topic'] ?? 'general' }} · <strong>Nodo:</strong> {{ $testResult['node_slug'] ?? 'ninguno' }} · <strong>Dominio negado:</strong> {{ $testResult['negated_domain'] ?? 'ninguno' }}</p>
                    @if($testResult['ambiguous'])<p class="mt-1 font-semibold text-amber-700">Dos candidatos cercanos: el chat pedirá aclaración.</p>@endif
                    <p class="mt-2"><strong>Respuesta:</strong> {{ $testResult['answer'] }}</p>
                    <p class="mt-2 text-xs"><strong>Fuente:</strong> {{ $testResult['source'] }} · <strong>Entidades:</strong> {{ $testResult['entities'] ? json_encode($testResult['entities'], JSON_UNESCAPED_UNICODE) : 'ninguna' }} · <strong>Acción:</strong> {{ $testResult['action'] }} · <strong>Tiempo de matching:</strong> {{ $testResult['duration_ms'] }} ms</p>
                    @if($testResult['suggestions'])<p class="mt-1 text-xs"><strong>Sugerencias:</strong> {{ implode(', ', array_column($testResult['suggestions'], 'label')) }}</p>@endif
                    <button type="button" wire:click="$toggle('showDebug')" class="mt-2 text-xs font-semibold text-science-blue underline">{{ $showDebug ? 'Ocultar evidencia' : 'Ver evidencia detallada' }}</button>
                    @if($showDebug)
                        <p class="mt-2 text-xs"><strong>Normalización:</strong> {{ $testResult['normal'] }} · <strong>Tokens:</strong> {{ implode(', ', $testResult['tokens']) ?: 'ninguno' }} · <strong>Sinónimos:</strong> {{ $testResult['synonyms'] ? json_encode($testResult['synonyms'], JSON_UNESCAPED_UNICODE) : 'ninguno' }}</p>
                        <div class="mt-2 max-h-56 overflow-auto">
                            @foreach($testResult['candidates'] as $candidate)
                                <p class="border-t border-blue-navy/10 py-1 text-xs"><strong>{{ $candidate['slug'] }} — {{ $candidate['score'] }}</strong><br>
                                    @foreach($candidate['components'] as $name => $value){{ $name }}: {{ round($value, 3) }}{{ !$loop->last ? ' · ' : '' }}@endforeach
                                </p>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
        </section>

        <section class="rounded-xl border border-border bg-white p-4 shadow-sm">
            <h2 class="font-display text-lg font-semibold text-blue-navy">Sinónimos</h2>
            <form wire:submit="addSynonym" class="mt-3 flex flex-wrap gap-2">
                <input type="text" wire:model="synonymTerm" maxlength="80" placeholder="Palabra utilizada" class="min-w-28 flex-1 rounded-lg border border-border px-3 py-2 text-sm" />
                <input type="text" wire:model="synonymCanonical" maxlength="80" placeholder="Forma canónica" class="min-w-28 flex-1 rounded-lg border border-border px-3 py-2 text-sm" />
                <button type="submit" class="rounded-lg bg-blue-navy px-4 py-2 text-sm font-semibold text-white">Añadir</button>
            </form>
            <div class="mt-3 flex max-h-40 flex-wrap gap-1.5 overflow-y-auto">
                @foreach($synonyms as $synonym)
                    <span class="rounded-full bg-[#F5F8FC] px-2.5 py-1 text-xs text-blue-navy">{{ $synonym->term }} → {{ $synonym->canonical }} <button type="button" wire:click="removeSynonym({{ $synonym->id }})" aria-label="Quitar sinónimo {{ $synonym->term }}" class="ml-1 text-red-700">×</button></span>
                @endforeach
            </div>
        </section>
    </div>

    <section class="rounded-xl border border-border bg-white p-4 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div><h2 class="font-display text-lg font-semibold text-blue-navy">Corpus y confusiones</h2><p class="text-xs text-text-secondary">TRAIN {{ $corpusSizes['train'] }} · CALIBRATION {{ $corpusSizes['calibration'] }} · TEST {{ $corpusSizes['test'] }}. TEST se reserva para evaluación final.</p></div>
            <div class="flex flex-wrap gap-2"><select wire:model="corpusSplit" aria-label="Partición del corpus" class="rounded-lg border border-border px-3 py-2 text-sm"><option value="train">TRAIN</option><option value="calibration">CALIBRATION</option><option value="test">TEST final</option></select><button type="button" wire:click="evaluateCorpus" class="rounded-lg border border-blue-navy/25 px-3 py-2 text-sm font-semibold text-blue-navy">Evaluar</button><button type="button" wire:click="calibrate" class="rounded-lg border border-blue-navy/25 px-3 py-2 text-sm font-semibold text-blue-navy">Calibrar sin publicar</button></div>
        </div>
        @if($confusion)
            <p class="mt-3 text-sm text-blue-navy">Top 1 {{ $confusion['correct'] }}/{{ $confusion['total'] }} ({{ round(100*$confusion['top1'], 1) }} %) · Top 2 {{ $confusion['top_two'] }}/{{ $confusion['total'] }} ({{ round(100*$confusion['top2'], 1) }} %) · Macro P/R/F1 {{ $confusion['macro_precision'] }} / {{ $confusion['macro_recall'] }} / {{ $confusion['macro_f1'] }} · F1 ponderado {{ $confusion['weighted_f1'] }} · Fuera de dominio {{ $confusion['ood_correct'] }}/{{ $confusion['ood_total'] }}</p>
            <div class="mt-2 max-h-48 overflow-auto"><table class="w-full text-left text-xs"><thead><tr><th>Intención</th><th>Casos</th><th>Precisión</th><th>Recall</th><th>F1</th></tr></thead><tbody>@foreach($confusion['per_class'] as $name => $metric)<tr class="border-t border-border"><td>{{ $name }}</td><td>{{ $metric['support'] }}</td><td>{{ $metric['precision'] }}</td><td>{{ $metric['recall'] }}</td><td>{{ $metric['f1'] }}</td></tr>@endforeach</tbody></table></div>
            <details class="mt-2 text-xs"><summary class="cursor-pointer font-semibold">Matriz de confusión y calibración de confianza</summary><div class="max-h-48 overflow-auto"><table class="w-full text-left"><thead><tr><th>Esperado</th><th>Obtenido</th><th>Casos</th></tr></thead><tbody>@foreach($confusion['matrix'] as $expected => $actuals)@foreach($actuals as $actual => $count)<tr class="border-t border-border"><td>{{ $expected }}</td><td>{{ $actual }}</td><td>{{ $count }}</td></tr>@endforeach @endforeach</tbody></table></div><table class="mt-2 w-full text-left"><thead><tr><th>Confianza</th><th>Casos</th><th>Aciertos</th><th>Exactitud</th></tr></thead><tbody>@foreach($confusion['bins'] as $range => $bin)<tr class="border-t border-border"><td>{{ $range }}</td><td>{{ $bin['cases'] }}</td><td>{{ $bin['correct'] }}</td><td>{{ $bin['accuracy'] === null ? 'sin muestra' : round($bin['accuracy']*100, 1).' %' }}</td></tr>@endforeach</tbody></table></details>
            <h3 class="mt-3 text-sm font-semibold text-blue-navy">Principales confusiones</h3>
            @foreach(array_slice($confusion['confusions'], 0, 10) as $pair)<p class="text-xs text-text-secondary">{{ $pair['expected'] }} → {{ $pair['actual'] }}: {{ $pair['count'] }}</p>@endforeach
            <h3 class="mt-3 text-sm font-semibold text-blue-navy">Hasta 20 errores</h3>
            @forelse($confusion['errors'] as $error)
                <p class="mt-1 border-t border-border py-1 text-xs text-text-secondary">«{{ $error['question'] }}»: <strong>{{ $error['expected'] }}</strong> → <strong>{{ $error['actual'] }}</strong>; score {{ $error['score'] }}; segundo {{ $error['second'] ?? 'ninguno' }} {{ $error['second_score'] }}; señal {{ $error['reason'] ?? 'ninguna' }}.</p>
            @empty
                <p class="mt-1 text-sm text-science-blue">Sin confusiones en este corpus.</p>
            @endforelse
        @endif
        @if($calibration)
            <div class="mt-4 rounded-lg border border-science-blue/20 bg-[#F5F8FC] p-3 text-sm text-blue-navy"><h3 class="font-semibold">Comparador A/B sin usuarios públicos</h3><p>Actual: Top 1 {{ round(100*$calibration['current']['top1'],1) }} %, Macro F1 {{ $calibration['current']['macro_f1'] }}, fuera de dominio {{ $calibration['current']['ood_correct'] }}/{{ $calibration['current']['ood_total'] }}.</p><p>Sugerida: Top 1 {{ round(100*$calibration['suggested']['top1'],1) }} %, Macro F1 {{ $calibration['suggested']['macro_f1'] }}, fuera de dominio {{ $calibration['suggested']['ood_correct'] }}/{{ $calibration['suggested']['ood_total'] }}.</p><p class="mt-1 text-xs">{{ $calibration['combinations'] }} combinaciones en CALIBRATION; no se modificó la configuración publicada.</p><p class="mt-1 text-xs">Pesos actuales: {{ json_encode($calibration['current_weights']) }}</p><p class="text-xs">Pesos sugeridos: {{ json_encode($calibration['suggested_weights']) }}</p><p class="text-xs">Umbrales sugeridos: {{ json_encode($calibration['suggested_thresholds']) }}</p><button type="button" wire:click="useSuggested" class="mt-2 rounded-lg border border-blue-navy px-3 py-1.5 font-semibold">Copiar sugerencia al formulario</button></div>
        @endif
    </section>

    <section class="rounded-xl border border-border bg-white p-4 shadow-sm">
        <h2 class="font-display text-lg font-semibold text-blue-navy">Ajustes del ranking</h2>
        <p class="mt-1 text-xs text-text-secondary">Los pesos se normalizan al guardar. La coincidencia textual sigue predominando sobre el historial.</p>
        <form wire:submit="saveRanking" class="mt-3 space-y-3 text-sm">
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5">
                @foreach($rankingWeights as $name => $weight)
                    <label class="text-blue-navy">{{ $name }}<input type="number" min="0" max="1" step="0.01" wire:model="rankingWeights.{{ $name }}" class="mt-1 w-full rounded border border-border px-2 py-1"></label>
                @endforeach
            </div>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5">
                @foreach($rankingThresholds as $name => $threshold)
                    <label class="text-blue-navy">{{ $name }}<input type="number" min="0" max="1" step="0.01" wire:model="rankingThresholds.{{ $name }}" class="mt-1 w-full rounded border border-border px-2 py-1"></label>
                @endforeach
            </div>
            @error('rankingWeights')<p class="text-xs text-red-700">{{ $message }}</p>@enderror
            @error('rankingThresholds')<p class="text-xs text-red-700">{{ $message }}</p>@enderror
            <label class="block text-blue-navy">Comentario de esta versión<input type="text" maxlength="240" wire:model="rankingComment" class="mt-1 w-full rounded border border-border px-2 py-1" /></label>
            <button type="submit" class="rounded-lg bg-blue-navy px-4 py-2 font-semibold text-white">Guardar ajustes</button>
        </form>
        <details class="mt-3 text-xs"><summary class="cursor-pointer font-semibold text-blue-navy">Versiones anteriores</summary>@foreach($rankingVersions as $version)<div class="flex flex-wrap items-center justify-between gap-2 border-t border-border py-1"><span>#{{ $version->id }} · {{ $version->created_at }} · usuario {{ $version->actor_id ?? 'sistema' }} · {{ $version->comment ?? 'Sin comentario' }}</span><button type="button" wire:click="restoreRanking({{ $version->id }})" wire:confirm="¿Restaurar esta configuración de pesos?" class="rounded border border-border px-2 py-1 font-semibold">Restaurar</button></div>@endforeach</details>
    </section>

    <section class="rounded-xl border border-border bg-white p-4 shadow-sm">
        <h2 class="font-display text-lg font-semibold text-blue-navy">Transferir conocimiento</h2>
        <p class="mt-1 text-xs text-text-secondary">El archivo contiene nodos, aliases, variantes, sinónimos y ranking. No incluye conversaciones, usuarios ni analítica. Los nodos importados quedan como borradores; los sinónimos y pesos se aplican al confirmar.</p>
        <div class="mt-3 flex flex-wrap items-center gap-3 text-sm">
            <button type="button" wire:click="downloadKnowledge" class="rounded-lg border border-blue-navy px-3 py-2 font-semibold text-blue-navy">Exportar JSON</button>
            <input type="file" wire:model="importFile" accept=".json,application/json" aria-label="Archivo JSON de conocimiento" class="max-w-full text-sm" />
            <button type="button" wire:click="previewImport" class="rounded-lg border border-border px-3 py-2 font-semibold">Previsualizar importación</button>
        </div>
        @error('importFile')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
        @if($importPreview)
            <p class="mt-3 text-sm text-blue-navy">{{ $importPreview['nodes'] }} nodos ({{ $importPreview['new_nodes'] }} nuevos), {{ $importPreview['synonyms'] }} sinónimos y configuración de ranking. Publica los borradores tras revisarlos.</p>
            <button type="button" wire:click="importKnowledge" wire:confirm="¿Importar el conocimiento previsualizado? Los nodos existentes conservarán su versión pública hasta publicar los borradores." class="mt-2 rounded-lg bg-blue-navy px-3 py-2 text-sm font-semibold text-white">Confirmar importación</button>
        @endif
    </section>

    <section class="rounded-xl border border-border bg-white p-4 shadow-sm">
        <h2 class="font-display text-lg font-semibold text-blue-navy">Preguntas por revisar</h2>
        <p class="mb-3 text-xs text-text-secondary">Las direcciones de correo y números se omiten antes de guardar una muestra. Asocia solo preguntas que tengan una respuesta institucional confirmada.</p>
        @forelse($unmatched as $question)
            <div class="flex flex-wrap items-center gap-2 border-t border-border py-2 text-sm">
                <span class="min-w-0 flex-1 break-words text-blue-navy"><strong>{{ $question->sample }}</strong> <small class="text-text-secondary">Ocurrencias: {{ $question->occurrences }} · Posible intención: {{ $question->candidate ?: 'ninguna' }} · Score: {{ $question->best_score }}</small><br><small class="text-text-secondary">Variantes: {{ implode(' · ', $question->variants) }}</small></span>
                <select wire:model="unmatchedNode.{{ $question->id }}" class="rounded-lg border border-border px-2 py-1 text-xs"><option value="">Asociar a…</option>@foreach($nodes as $node)<option value="{{ $node->id }}">{{ $node->title }}</option>@endforeach</select>
                <button type="button" wire:click="associate({{ $question->id }})" class="rounded-lg border border-blue-navy/20 px-2 py-1 text-xs font-semibold text-blue-navy">Guardar alias</button>
                <button type="button" wire:click="createFromUnmatched({{ $question->id }})" class="rounded-lg border border-blue-navy/20 px-2 py-1 text-xs text-blue-navy">Crear nodo</button>
                <button type="button" wire:click="markUnmatched({{ $question->id }}, 'resolved')" class="rounded-lg border border-blue-navy/20 px-2 py-1 text-xs text-blue-navy">Resuelta</button>
                <button type="button" wire:click="markUnmatched({{ $question->id }}, 'ignored')" class="rounded-lg border border-blue-navy/20 px-2 py-1 text-xs text-blue-navy">Ignorar</button>
            </div>
        @empty
            <p class="text-sm text-text-secondary">No hay preguntas pendientes.</p>
        @endforelse
    </section>
</div>
