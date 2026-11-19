<div class="hub-workspace space-y-4" data-editor-wizard x-data="wizardVisualEditor($wire, @js($mapa), @js('wizard:'.$pasoEditor), @js(\App\Support\EditorPortal::version(\App\Support\DisposicionContenido::clave('wizard:'.$pasoEditor))), @js((object) \App\Support\DisposicionContenido::leer('wizard:'.$pasoEditor)))" wire:key="wizard-editor-{{ $pasoEditor }}-{{ $modalidadEditor }}">
    <header><h2 class="hub-section-title">Edita el formulario en su ubicación</h2><p class="mt-2 text-sm text-text-secondary">Recorre los mismos pasos y selecciona un título, etiqueta o botón. Esta vista usa las pantallas del wizard con datos de ejemplo; los controles sirven para elegir textos.</p></header>
    @if(session('wizard-textos'))<p role="status" class="hub-panel p-3 text-success">{{ session('wizard-textos') }}</p>@endif
    <div class="hub-panel hub-editor-filtros grid items-start gap-4 p-4 sm:grid-cols-2">
        <flux:select label="Paso que quieres editar" wire:model.live="pasoEditor">@foreach($pasosEditor as $id=>$paso)<option value="{{ $id }}">{{ $loop->iteration }}. {{ $paso[0] }}</option>@endforeach</flux:select>
        <flux:select label="Modalidad de la vista previa" wire:model.live="modalidadEditor"><option>Depósito</option><option>Donación</option></flux:select>
    </div>
    <nav class="hub-editor-pasos" aria-label="Pasos para editar textos">@foreach($pasosEditor as $id=>$paso)<button type="button" wire:click="$set('pasoEditor', {{ $id }})" @if($id===$pasoEditor) aria-current="step" @endif><span aria-hidden="true">{{ $loop->iteration }}</span>{{ $paso[0] }}</button>@endforeach</nav>
    @include('livewire.administracion.partials.herramientas-disposicion')
    <section x-show="opciones.length" x-cloak class="hub-panel p-3" aria-label="Elegir ubicación del texto del wizard"><p>Este texto tiene varias ubicaciones. Selecciona la que deseas cambiar.</p>@foreach($mapa as $clave=>$valor)<button type="button" x-show="opciones.includes(@js($clave))" x-on:click="editar(@js($clave))" class="block min-h-11 p-2 text-left underline">{{ str_starts_with($clave,'literal:')?'Texto compartido del sistema':ucfirst(explode('.',$clave)[0]).' · '.str_replace('_',' ',explode('.',$clave)[1]) }}: {{ \Illuminate\Support\Str::limit($valor,100) }}</button>@endforeach</section>
    <section class="hub-panel hub-editor-scroll p-4" tabindex="0" aria-label="Vista previa del wizard" data-wizard-vista x-ref="vistaWizard" x-on:click.capture="seleccionar($event)" x-on:submit.capture.prevent.stop>
        <div class="hub-transactional-ui hub-depositos-ui space-y-3">
            <h2 class="hub-request-page-title">{{ \App\Support\WizardCopy::text('general.titulo') }}</h2>
            @php ob_start(); @endphp
            @include('gestionprestamosrecepciones::investigador.registro-solicitud-deposito.paso-'.$pasosEditor[$pasoEditor][1], $datosVista)
            @php $fragmento=\App\Support\VistaPreviaWizard::sanear(ob_get_clean(),$modalidadEditor); @endphp
            <div data-wizard-fragmento data-disposicion-scope="wizard:{{ $pasoEditor }}">{!! $fragmento !!}</div>
            <div class="flex items-center justify-between border-t border-border pt-3"><button type="button" class="hub-ig-button">{{ \App\Support\WizardCopy::text('general.atras') }}</button>@if(is_string(config('wizard-copy.'.$pasosEditor[$pasoEditor][2].'.accion')))<button type="button" class="hub-ig-button">{{ \App\Support\WizardCopy::text($pasosEditor[$pasoEditor][2].'.accion') }}</button>@endif</div>
        </div>
    </section>
    <details class="hub-panel p-4">
        <summary class="cursor-pointer font-semibold">Otros estados y textos de este paso</summary>
        <p class="my-3 text-sm text-text-secondary">Incluye ayudas, avisos y estados que aparecen según las respuestas y archivos del solicitante.</p>
        <ul class="divide-y divide-border">
        @foreach($grupos as $grupo=>$campos)
            @if(in_array($grupo,[$pasosEditor[$pasoEditor][2],'general','portal'],true))
                @foreach($campos as $nombre=>$defecto)
                    <li><button type="button" wire:click="seleccionarTexto('{{ $grupo }}.{{ $nombre }}')" class="block min-h-11 w-full p-3 text-left text-sm"><span class="block font-semibold">{{ ucfirst($grupo) }} · {{ str_replace('_',' ',$nombre) }}</span><span>{{ $textos[$grupo][$nombre] }}</span></button></li>
                @endforeach
            @endif
        @endforeach
        </ul>
    </details>
    <flux:modal wire:model="editorAbierto" aria-labelledby="wizard-editar-titulo" class="w-full max-w-xl">
        <form wire:submit="guardarSeleccion" class="space-y-4" data-copy-preserve><h2 id="wizard-editar-titulo" class="hub-section-title">Editar texto del formulario</h2>
            <p class="text-sm"><strong>Ubicación:</strong> Formulario de depósitos → {{ $pasosEditor[$pasoEditor][0] }} → {{ str_starts_with($claveSeleccionada,'sistema:')?'Texto compartido del sistema':str_replace(['.','_'],' ',$claveSeleccionada) }}</p>
            @if(str_starts_with($claveSeleccionada,'sistema:'))<p class="rounded border border-border p-3 text-sm">Este texto se comparte con otras pantallas. El cambio se aplica a todas sus apariciones iguales; las ubicaciones se pueden consultar en «Textos generales del sistema».</p>@endif
            <p class="whitespace-pre-line text-sm"><strong>Original:</strong> {{ $originalSeleccionado }}</p><flux:textarea wire:model="textoActual" label="Texto que verá el solicitante" rows="5" maxlength="1000" required/><flux:error name="textoActual"/>
            <div class="flex flex-wrap justify-end gap-3"><flux:button type="button" wire:click="$set('editorAbierto', false)">Cancelar</flux:button><flux:button type="submit" variant="primary">Guardar y publicar texto</flux:button></div>
        </form>
    </flux:modal>
</div>
