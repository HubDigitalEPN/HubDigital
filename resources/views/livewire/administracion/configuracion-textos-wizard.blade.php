@php
    $posicionesPorPaso = [];
    $clavesPorPaso = [];
    foreach ($pasosEditor as $id => $paso) {
        $posicionesPorPaso[$id] = (object) \App\Support\DisposicionContenido::leer('wizard:'.$id);
        $clavesPorPaso[$id] = 'pasos.'.$paso[1];
    }
    $pasosRender = [$pasoEditor => $pasosEditor[$pasoEditor]] + $pasosEditor;
    $versionPlantillas = hash('sha256', serialize($mapa));
@endphp
<div class="hub-workspace space-y-4" data-editor-wizard x-data="wizardVisualEditor($wire, @js($mapa), @js($pasoEditor), @js($posicionesPorPaso), @js($originales), @js($clavesPorPaso))" wire:key="wizard-editor">
    <p class="sr-only">Haz doble clic en un texto para editarlo. En los nombres de los pasos también puedes pulsar F2. Cambia la modalidad en las tarjetas del paso Trámite.</p>
    @if(session('wizard-textos'))<p role="status" class="hub-panel p-3 text-success">{{ session('wizard-textos') }}</p>@endif
    <nav class="hub-editor-pasos" aria-label="Pasos para editar textos" x-on:click.capture="navegarPaso($event)" x-on:dblclick.capture="editarPaso($event)" x-on:keydown.f2.capture="editarPaso($event)">
        @foreach($pasosEditor as $id=>$paso)
            <button type="button" data-wizard-paso="{{ $id }}" data-wizard-copy="pasos.{{ $paso[1] }}" aria-keyshortcuts="F2" x-bind:aria-current="pasoActivo === {{ $id }} ? 'step' : null" @if($id===$pasoEditor) aria-current="step" @endif><span aria-hidden="true">{{ $loop->iteration }}</span>{{ $paso[0] }}</button>
        @endforeach
    </nav>
    <section wire:key="wizard-opciones-texto" x-show="opciones.length" x-cloak class="hub-panel p-3" aria-label="Elegir ubicación del texto del wizard"><p>Este texto tiene varias ubicaciones. Selecciona la que deseas cambiar.</p>@foreach($mapa as $clave=>$valor)<button type="button" x-show="opciones.includes(@js($clave))" x-on:click="editar(@js($clave))" class="block min-h-11 p-2 text-left underline">{{ str_starts_with($clave,'literal:')?'Texto compartido del sistema':ucfirst(explode('.',$clave)[0]).' · '.str_replace('_',' ',explode('.',$clave)[1]) }}: {{ \Illuminate\Support\Str::limit($valor,100) }}</button>@endforeach</section>
    <section wire:key="wizard-vista-textos" class="hub-panel hub-editor-scroll p-4" tabindex="0" aria-label="Vista previa del wizard" data-wizard-vista x-ref="vistaWizard" x-on:click.capture="clic($event)" x-on:dblclick.capture="seleccionar($event)" x-on:keydown.capture="teclado($event)" x-on:submit.capture.prevent.stop>
        <div class="hub-transactional-ui hub-depositos-ui space-y-3">
            <h2 class="hub-request-page-title" data-wizard-copy="general.titulo">{{ \App\Support\WizardCopy::text('general.titulo') }}</h2>
            <div wire:key="wizard-plantillas-{{ $pasoEditor }}-{{ $modalidadEditor }}-{{ $versionPlantillas }}" x-init="$nextTick(() => preparar())">
                @foreach($pasosRender as $id => $paso)
                    @php
                        $vistaPaso = $id === $pasoEditor ? $datosVista : \App\Support\VistaPreviaWizard::datos($id, $modalidadEditor);
                        $claveAccion = $paso[2].'.accion';
                        ob_start();
                    @endphp
                    @include('gestionprestamosrecepciones::investigador.registro-solicitud-deposito.paso-'.$paso[1], $vistaPaso + ['soloEdicionDeTextos' => true])
                    @php $fragmento = \App\Support\VistaPreviaWizard::sanear(ob_get_clean(), $modalidadEditor); @endphp
                    <template x-if="pasoActivo === {{ $id }}">
                        <div class="space-y-3">
                            <div data-wizard-fragmento data-disposicion-scope="wizard:{{ $id }}">{!! $fragmento !!}</div>
                            <div class="flex items-center justify-between border-t border-border pt-3">
                                <button type="button" class="hub-ig-button" data-wizard-copy="general.atras">{{ \App\Support\WizardCopy::text('general.atras') }}</button>
                                @if(is_string(config('wizard-copy.'.$claveAccion)))
                                    <button type="button" class="hub-ig-button" data-wizard-copy="{{ $claveAccion }}">{{ \App\Support\WizardCopy::text($claveAccion) }}</button>
                                @endif
                            </div>
                        </div>
                    </template>
                @endforeach
            </div>
        </div>
    </section>
    <flux:modal name="wizard-texto-inmediato" aria-labelledby="wizard-editar-titulo" class="w-full max-w-xl">
        <form x-on:submit.prevent="publicarTexto()" class="space-y-4" data-copy-preserve><h2 id="wizard-editar-titulo" class="hub-section-title">Editar texto del formulario</h2>
            <p class="text-sm"><strong>Ubicación:</strong> Formulario de depósitos → <span x-text="mapa[claveLocal.startsWith('pasos.') ? claveLocal : clavesPorPaso[pasoActivo]]"></span> → <span x-text="claveLocal?.startsWith('literal:') ? 'Texto compartido del sistema' : (claveLocal || '').replaceAll(/[._]/g, ' ')"></span></p>
            <p x-show="claveLocal?.startsWith('literal:')" class="rounded border border-border p-3 text-sm">Este texto se comparte con otras pantallas. El cambio se aplica a todas sus apariciones iguales; las ubicaciones se pueden consultar en «Textos generales del sistema».</p>
            <p role="alert" x-show="errorSeleccion" x-text="errorSeleccion"></p>
            <p class="whitespace-pre-line text-sm"><strong>Original:</strong> <span x-text="originalLocal"></span></p>
            <label wire:ignore class="grid gap-2 text-sm font-semibold">Texto que verá el solicitante<textarea x-model="textoLocal" x-bind:disabled="publicando" rows="5" maxlength="1000" required class="w-full rounded border border-border bg-surface p-3"></textarea></label>
            <div class="flex flex-wrap justify-end gap-3"><flux:button type="button" x-on:click="$flux.modal('wizard-texto-inmediato').close()">Cancelar</flux:button><flux:button type="submit" variant="primary" :loading="false" x-bind:disabled="publicando">Guardar y publicar texto</flux:button></div>
        </form>
    </flux:modal>
</div>
