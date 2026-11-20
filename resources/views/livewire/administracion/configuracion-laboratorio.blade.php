<div class="hub-workspace space-y-3" data-editor-laboratorio
     x-data="laboratorioEditor($wire, @js(collect(config('portal-copy.navegacion'))->mapWithKeys(fn ($v, $k) => ['navegacion.'.$k => \App\Support\PortalCopy::text('navegacion.'.$k)])), @js([(parse_url(route('home'), PHP_URL_PATH) ?: '/') => route('admin.contenidos'), parse_url(route('portal.catalogo'), PHP_URL_PATH) => route('admin.contenidos', ['grupo' => 'catalogo']), parse_url(route('depositos.portal'), PHP_URL_PATH) => route('admin.contenidos', ['grupo' => 'depositos'])]))"
     x-on:laboratorio-publicado.window="recargar()" x-on:foto-editorial-publicada.window="recargar()" x-on:laboratorio-bloque-retirado.window="retirarBloque($event.detail.id)">
    <p class="sr-only">Usa doble clic o Intro para editar un texto o una fotografía. Usa Añadir contenido para crear un bloque.</p>
    @if(session('laboratorio-guardado'))<p role="status" data-hub-feedback="success" class="hub-panel p-3 text-success">{{ session('laboratorio-guardado') }}</p>@endif
    @if($errors->any())<div role="alert" data-hub-feedback="error" class="hub-panel p-3 text-error"><p>No se publicaron los cambios:</p><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @include('livewire.administracion.partials.foto-editorial')
    @include('livewire.administracion.partials.texto-navegacion')
    <form wire:submit="guardar" class="space-y-3">
        <section class="hub-panel overflow-hidden" aria-label="Editor visual de El Laboratorio">
            <div class="lab-layout-frame" wire:ignore><iframe sandbox="allow-same-origin" inert style="pointer-events:none" x-ref="vista" src="{{ route('laboratorio') }}?_editor=1&edicion=1" title="Editor visual de El Laboratorio"></iframe></div>
        </section>
        <div class="flex flex-wrap items-center gap-3">
            <flux:button type="button" wire:click="crearBloqueVisual" wire:loading.attr="disabled">Añadir contenido</flux:button>
            <button type="submit" wire:loading.attr="disabled" class="rounded-lg bg-science-blue px-5 py-3 text-sm font-semibold text-white">Guardar y publicar</button>
            <span role="status" class="text-sm text-text-secondary" x-text="mensaje"></span>
            <span wire:loading wire:target="guardar,publicarTextoVisual" role="status" class="text-sm text-text-secondary">Guardando contenido y fotografías…</span>
        </div>
    </form>
    <flux:modal name="laboratorio-texto-inmediato" aria-labelledby="lab-texto-inmediato-titulo" class="w-full max-w-xl">
        <form x-on:submit.prevent="publicarTexto()" class="space-y-4" data-copy-preserve>
            <h2 id="lab-texto-inmediato-titulo" class="hub-section-title">Editar texto</h2>
            <template x-if="campoTexto === 'personal_titulo'"><label class="lab-admin-field">Palabra destacada<input x-model="complemento" maxlength="5000"></label></template>
            <label class="lab-admin-field">Contenido<textarea x-model="textoLocal" x-bind:disabled="publicandoTexto" rows="7" maxlength="20000" required></textarea></label>
            <template x-if="campoTexto === 'adrian_bio'"><label class="lab-admin-field">Especialidad<textarea x-model="complemento" maxlength="20000"></textarea></label></template>
            <template x-if="campoTexto === 'contacto_accion'"><label class="lab-admin-field">Correo de contacto<input type="email" x-model="complemento" maxlength="5000"></label></template>
            <p role="alert" x-show="errorTexto" x-text="errorTexto"></p>
            <div class="flex flex-wrap justify-end gap-3">
                <flux:button type="button" x-on:click="$flux.modal('laboratorio-texto-inmediato').close()">Cancelar</flux:button>
                <flux:button type="submit" variant="primary" :loading="false" x-bind:disabled="publicandoTexto">Guardar y publicar</flux:button>
            </div>
        </form>
    </flux:modal>
    <flux:modal wire:model="textoAbierto" aria-labelledby="lab-texto-seleccionado" class="w-full max-w-xl">
        @if($campoSeleccionado !== '')
            <form wire:submit="guardar" class="space-y-4">
                <h2 id="lab-texto-seleccionado" class="hub-section-title">Editar texto</h2>
                @if($campoSeleccionado === 'personal_titulo')<label class="lab-admin-field">Palabra destacada<input wire:model="textos.personal_destacado" maxlength="5000"></label>@endif
                <label class="lab-admin-field">Contenido<textarea rows="7" maxlength="20000" wire:model="{{ $modeloSeleccionado }}"></textarea></label>
                @if($campoSeleccionado === 'adrian_bio')<label class="lab-admin-field">Especialidad<textarea wire:model="textos.adrian_especialidad" maxlength="20000"></textarea></label>@endif
                @if($campoSeleccionado === 'contacto_accion')<label class="lab-admin-field">Correo de contacto<input type="email" wire:model="textos.contacto_email" maxlength="5000"></label>@endif
                @error($modeloSeleccionado)<p role="alert" class="text-error">{{ $message }}</p>@enderror
                <div class="flex flex-wrap justify-end gap-3">
                    <flux:button type="button" wire:click="$set('textoAbierto', false)">Cancelar</flux:button>
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled">Guardar y publicar</flux:button>
                </div>
            </form>
        @endif
    </flux:modal>
    <flux:modal wire:model="fotoAbierta" aria-labelledby="lab-foto-seleccionada" class="w-full max-w-xl">
        @if($fotoSeleccionada !== '')
            <form wire:submit="guardar" class="space-y-4" wire:key="editar-foto-{{ $fotoSeleccionada }}">
                <h2 id="lab-foto-seleccionada" class="hub-section-title">Cambiar fotografía</h2>
                <x-editor-foto-input id="lab-foto-archivo" :modelo="$modeloFoto" etiqueta="Nueva fotografía" />
                <p class="text-sm text-text-secondary">JPG, PNG, WebP o AVIF de hasta 8 MB.</p>
                @error($modeloFoto)<p role="alert" class="text-error">{{ $message }}</p>@enderror
                <label class="lab-admin-field">Descripción accesible<input wire:model="{{ $modeloAlternativo }}" maxlength="180" required></label>
                <label class="flex items-start gap-2 text-sm"><input type="checkbox" wire:model="{{ $modeloRestaurarFoto }}">{{ $fotoBloque ? 'Quitar fotografía publicada' : 'Usar fotografía original del diseño' }}</label>
                <div class="flex flex-wrap justify-end gap-3"><flux:button type="button" wire:click="$set('fotoAbierta', false)">Cancelar</flux:button><flux:button type="submit" variant="primary" wire:loading.attr="disabled">Guardar y publicar</flux:button></div>
            </form>
        @endif
    </flux:modal>
    <flux:modal wire:model="bloqueAbierto" aria-labelledby="lab-bloque-seleccionado" class="w-full max-w-xl">
        @if($indiceBloque !== false)
            <form wire:submit="guardar" class="space-y-4" wire:key="editar-bloque-{{ $bloqueSeleccionado }}">
                <h2 id="lab-bloque-seleccionado" class="hub-section-title">{{ $bloqueNuevo ? 'Nuevo contenido' : 'Editar contenido' }}</h2>
                <label class="lab-admin-field">Título (opcional)<input wire:model="bloques.{{ $indiceBloque }}.titulo" maxlength="180"></label>
                <label class="lab-admin-field">Texto (opcional)<textarea wire:model="bloques.{{ $indiceBloque }}.texto" rows="6" maxlength="20000"></textarea></label>
                @error('bloques.'.$indiceBloque.'.texto')<p role="alert" class="text-error">{{ $message }}</p>@enderror
                <x-editor-foto-input id="lab-bloque-foto-archivo" :modelo="'fotosBloques.'.$bloqueSeleccionado" etiqueta="Fotografía (opcional)" />
                @error('fotosBloques.'.$bloqueSeleccionado)<p role="alert" class="text-error">{{ $message }}</p>@enderror
                <label class="lab-admin-field">Descripción accesible de la fotografía<input wire:model="bloques.{{ $indiceBloque }}.alt" maxlength="180"></label>
                @unless($bloqueNuevo)<label class="flex items-start gap-2 text-sm"><input type="checkbox" wire:model="quitarFotosBloques.{{ $bloqueSeleccionado }}">Quitar fotografía publicada</label>@endunless
                <div class="flex flex-wrap justify-end gap-3">
                    @unless($bloqueNuevo)<flux:button type="button" wire:click="quitarBloqueVisual">Quitar este contenido</flux:button>@endunless
                    <flux:button type="button" wire:click="cancelarBloqueVisual">Cancelar</flux:button>
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled">Guardar y publicar</flux:button>
                </div>
            </form>
        @endif
    </flux:modal>
</div>
