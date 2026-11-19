<div class="hub-workspace space-y-4" data-editor-wizard x-data="wizardVisualEditor($wire, @js($mapa), @js('wizard:'.$pasoEditor), @js(\App\Support\EditorPortal::version(\App\Support\DisposicionContenido::clave('wizard:'.$pasoEditor))), @js((object) \App\Support\DisposicionContenido::leer('wizard:'.$pasoEditor)))" wire:key="wizard-editor-{{ $pasoEditor }}-{{ $modalidadEditor }}">
    <p class="sr-only">Haz doble clic en un texto para editarlo y arrastra un componente para moverlo. Cambia la modalidad en las tarjetas del paso Trámite.</p>
    @if(session('wizard-textos'))<p role="status" class="hub-panel p-3 text-success">{{ session('wizard-textos') }}</p>@endif
    <nav class="hub-editor-pasos" aria-label="Pasos para editar textos">@foreach($pasosEditor as $id=>$paso)<button type="button" wire:click="$set('pasoEditor', {{ $id }})" @if($id===$pasoEditor) aria-current="step" @endif><span aria-hidden="true">{{ $loop->iteration }}</span>{{ $paso[0] }}</button>@endforeach</nav>
    <section x-show="opciones.length" x-cloak class="hub-panel p-3" aria-label="Elegir ubicación del texto del wizard"><p>Este texto tiene varias ubicaciones. Selecciona la que deseas cambiar.</p>@foreach($mapa as $clave=>$valor)<button type="button" x-show="opciones.includes(@js($clave))" x-on:click="editar(@js($clave))" class="block min-h-11 p-2 text-left underline">{{ str_starts_with($clave,'literal:')?'Texto compartido del sistema':ucfirst(explode('.',$clave)[0]).' · '.str_replace('_',' ',explode('.',$clave)[1]) }}: {{ \Illuminate\Support\Str::limit($valor,100) }}</button>@endforeach</section>
    <section class="hub-panel hub-editor-scroll p-4" tabindex="0" aria-label="Vista previa del wizard" data-wizard-vista x-ref="vistaWizard" x-on:click.capture="clic($event)" x-on:dblclick.capture="seleccionar($event)" x-on:keydown.capture="teclado($event)" x-on:submit.capture.prevent.stop>
        <div class="hub-transactional-ui hub-depositos-ui space-y-3">
            <h2 class="hub-request-page-title">{{ \App\Support\WizardCopy::text('general.titulo') }}</h2>
            @php ob_start(); @endphp
            @include('gestionprestamosrecepciones::investigador.registro-solicitud-deposito.paso-'.$pasosEditor[$pasoEditor][1], $datosVista)
            @php $fragmento=\App\Support\VistaPreviaWizard::sanear(ob_get_clean(),$modalidadEditor); @endphp
            <div data-wizard-fragmento data-disposicion-scope="wizard:{{ $pasoEditor }}">{!! $fragmento !!}</div>
            <div class="flex items-center justify-between border-t border-border pt-3"><button type="button" class="hub-ig-button">{{ \App\Support\WizardCopy::text('general.atras') }}</button>@if(is_string(config('wizard-copy.'.$pasosEditor[$pasoEditor][2].'.accion')))<button type="button" class="hub-ig-button">{{ \App\Support\WizardCopy::text($pasosEditor[$pasoEditor][2].'.accion') }}</button>@endif</div>
        </div>
    </section>
    @include('livewire.administracion.partials.herramientas-disposicion')
    <flux:modal name="wizard-texto-inmediato" wire:model="editorAbierto" aria-labelledby="wizard-editar-titulo" class="w-full max-w-xl">
        <form x-on:submit.prevent="publicarTexto()" class="space-y-4" data-copy-preserve><h2 id="wizard-editar-titulo" class="hub-section-title">Editar texto del formulario</h2>
            <p class="text-sm"><strong>Ubicación:</strong> Formulario de depósitos → {{ $pasosEditor[$pasoEditor][0] }} → <span x-text="claveLocal?.startsWith('literal:') ? 'Texto compartido del sistema' : (claveLocal || '').replaceAll(/[._]/g, ' ')"></span></p>
            <p x-show="claveLocal?.startsWith('literal:')" class="rounded border border-border p-3 text-sm">Este texto se comparte con otras pantallas. El cambio se aplica a todas sus apariciones iguales; las ubicaciones se pueden consultar en «Textos generales del sistema».</p>
            <p role="status" x-show="seleccionOcupada || errorSeleccion" x-text="errorSeleccion || 'Cargando el texto guardado…'"></p>
            <p class="whitespace-pre-line text-sm"><strong>Original:</strong> <span x-text="originalLocal"></span></p>
            <label wire:ignore class="grid gap-2 text-sm font-semibold">Texto que verá el solicitante<textarea x-model="textoLocal" x-bind:disabled="seleccionOcupada || !!errorSeleccion || publicando" rows="5" maxlength="1000" required class="w-full rounded border border-border bg-surface p-3"></textarea></label><flux:error name="textoActual"/>
            <div class="flex flex-wrap justify-end gap-3"><flux:button type="button" x-on:click="$flux.modal('wizard-texto-inmediato').close()">Cancelar</flux:button><flux:button type="submit" variant="primary" :loading="false" x-bind:disabled="seleccionOcupada || !!errorSeleccion || publicando">Guardar y publicar texto</flux:button></div>
        </form>
    </flux:modal>
</div>
