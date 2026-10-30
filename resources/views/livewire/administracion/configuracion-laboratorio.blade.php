<div class="hub-workspace mx-auto max-w-7xl space-y-5 p-3 sm:p-5">
    <header class="hub-page-header">
        <div><h1 class="hub-page-title">Página El Laboratorio</h1><p class="mt-2 text-sm text-text-secondary">Edita la presentación, los perfiles y cada fotografía de la página pública.</p></div>
        <a href="{{ route('laboratorio') }}" target="_blank" rel="noopener" class="text-sm font-semibold text-science-blue underline">Ver página pública</a>
    </header>
    @if(session('laboratorio-guardado'))<p role="status" data-hub-feedback="success" class="hub-panel p-4 text-success">{{ session('laboratorio-guardado') }}</p>@endif
    @if($errors->any())<div role="alert" data-hub-feedback="error" class="hub-panel p-4 text-error"><p>No se publicaron los cambios:</p><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form wire:submit="guardar" class="space-y-5">
        @php($catalogoDiseno = array_map(fn ($elemento) => array_intersect_key($elemento, array_flip(['tipo', 'etiqueta'])), \App\Services\DisenoLaboratorio::elementos($pagina)))
        <section class="hub-panel space-y-4 p-4" aria-labelledby="lab-layout-title" wire:ignore x-data="laboratorioEditor($wire, @js($posiciones), @js($catalogoDiseno))" x-on:laboratorio-publicado.window="recargar()">
            <h2 id="lab-layout-title" class="hub-section-title">Posición libre de fotos y textos</h2>
            <p class="text-sm text-text-secondary">Selecciona y arrastra cualquier elemento. Puedes cambiar su tamaño y colocarlo encima o debajo de otros. Guarda primero los nuevos textos o fotos para verlos aquí. En móviles, el contenido se ordena de arriba abajo para mantener su lectura.</p>
            <div class="lab-layout-controls">
                <label class="lab-admin-field">Elemento<select x-model="seleccion" x-on:change="elegir(seleccion)"><option value="">Selecciona un elemento</option><template x-for="(elemento,id) in catalogo" :key="id"><option :value="id" x-text="elemento.etiqueta"></option></template></select></label>
                <template x-if="seleccion && posiciones[seleccion]">
                    <div class="lab-layout-values">
                        <label class="lab-admin-field">Horizontal (%)<input type="number" min="0" max="95" step="any" x-model.number="posiciones[seleccion].x" x-on:change="cambiar()"></label>
                        <label class="lab-admin-field">Vertical<input type="number" min="0" max="5000" step="any" x-model.number="posiciones[seleccion].y" x-on:change="cambiar()"></label>
                        <label class="lab-admin-field">Ancho (%)<input type="number" min="5" max="100" step="any" x-model.number="posiciones[seleccion].ancho" x-on:change="cambiar()"></label>
                        <label class="lab-admin-field" x-show="catalogo[seleccion].tipo === 'foto'">Alto<input type="number" min="1" max="500" step="any" x-model.number="posiciones[seleccion].alto" x-on:change="cambiar()"></label>
                        <label class="lab-admin-field" x-show="catalogo[seleccion].tipo !== 'foto'">Tamaño de texto<input type="number" min="0.5" max="8" step="any" x-model.number="posiciones[seleccion].fuente" x-on:change="cambiar()"></label>
                        <label class="lab-admin-field">Capa<input type="number" min="0" max="20" step="1" x-model.number="posiciones[seleccion].capa" x-on:change="cambiar()"></label>
                    </div>
                </template>
            </div>
            <p role="status" class="text-sm text-text-secondary" x-text="mensaje"></p>
            <div class="lab-layout-frame"><iframe x-ref="vista" x-on:load="iniciar()" src="{{ route('laboratorio') }}?edicion=1" title="Editor visual de El Laboratorio"></iframe></div>
            <button type="button" class="lab-admin-add" x-on:click="restaurar()">Restaurar composición original</button>
        </section>
        @php($grupos = [
            'Presentación' => ['titulo' => 'Título de la página', 'principal' => 'Texto principal'],
            'Nuestro personal' => ['personal_destacado' => 'Palabra destacada del título', 'personal_titulo' => 'Resto del título', 'personal_descripcion' => 'Presentación del equipo'],
            'Perfil de Adrian' => ['adrian_nombre' => 'Nombre', 'adrian_cargo' => 'Cargo', 'adrian_bio' => 'Presentación breve', 'adrian_especialidad' => 'Especialidad'],
            'Perfil de Vladimir' => ['vladimir_nombre' => 'Nombre', 'vladimir_cargo' => 'Cargo', 'vladimir_bio' => 'Descripción'],
            'Perfil de David' => ['david_nombre' => 'Nombre', 'david_cargo' => 'Cargo', 'david_bio' => 'Descripción'],
            'Colaboración y contacto' => ['colaboracion_titulo' => 'Título de colaboración', 'colaboracion_texto' => 'Invitación', 'contacto_titulo' => 'Pregunta de contacto', 'contacto_accion' => 'Texto del enlace', 'contacto_email' => 'Correo del curador'],
        ])
        @foreach($grupos as $grupo => $campos)
            <fieldset class="hub-panel space-y-4 p-4"><legend class="hub-section-title">{{ $grupo }}</legend>
                @foreach($campos as $clave => $etiqueta)
                    <label class="lab-admin-field">{{ $etiqueta }}
                        @if(in_array($clave, ['principal', 'personal_descripcion', 'adrian_bio', 'adrian_especialidad', 'vladimir_bio', 'david_bio', 'colaboracion_texto'], true))
                            <textarea wire:model="textos.{{ $clave }}" rows="{{ $clave === 'principal' ? 7 : 3 }}" maxlength="20000" required></textarea>
                        @else
                            <input type="{{ $clave === 'contacto_email' ? 'email' : 'text' }}" wire:model="textos.{{ $clave }}" maxlength="5000" required>
                        @endif
                    </label>
                @endforeach
            </fieldset>
        @endforeach
        <section class="hub-panel space-y-4 p-4" aria-labelledby="lab-photo-editor-title">
            <h2 id="lab-photo-editor-title" class="hub-section-title">Fotografías</h2>
            <p class="text-sm text-text-secondary">JPG, PNG, WebP o AVIF de hasta 8 MB. Describe cada fotografía para lectores de pantalla. Los cambios se publican al guardar.</p>
            <div class="lab-admin-photos">
                @foreach($pagina['fotos'] as $clave => $foto)
                    <fieldset class="rounded-md border border-border p-3" wire:key="foto-laboratorio-{{ $clave }}"><legend class="text-sm font-semibold">{{ $foto['etiqueta'] }}</legend>
                        <div class="lab-admin-preview"><x-laboratorio-foto :clave="$clave" :foto="$foto" /></div>
                        <label class="lab-admin-field">Nueva fotografía<input type="file" wire:model="fotos.{{ $clave }}" accept="image/jpeg,image/png,image/webp,image/avif"></label>
                        <label class="lab-admin-field mt-3">Descripción accesible<input wire:model="alternativos.{{ $clave }}" maxlength="180" required></label>
                        <label class="mt-3 flex items-start gap-2 text-sm"><input type="checkbox" wire:model="restaurarFotos.{{ $clave }}">Usar fotografía original del diseño</label>
                    </fieldset>
                @endforeach
            </div>
        </section>
        <section class="hub-panel space-y-4 p-4" aria-labelledby="lab-extra-editor-title">
            <h2 id="lab-extra-editor-title" class="hub-section-title">Más fotografías y contenido</h2>
            <p class="text-sm text-text-secondary">Añade hasta 30 bloques. Cada bloque admite texto, una fotografía o ambos. Puedes cambiar el orden; todo se publica al guardar. Los textos admiten hasta 20.000 caracteres y muestran las primeras 300 palabras antes de «Leer más».</p>
            @foreach($bloques as $indice => $bloque)
                <fieldset class="lab-admin-block" wire:key="bloque-laboratorio-{{ $bloque['id'] }}">
                    <legend>Bloque {{ $indice + 1 }}</legend>
                    <div class="lab-admin-block-actions">
                        <button type="button" wire:click="moverBloque('{{ $bloque['id'] }}', -1)" @disabled($loop->first) aria-label="Subir bloque {{ $indice + 1 }}">Subir</button>
                        <button type="button" wire:click="moverBloque('{{ $bloque['id'] }}', 1)" @disabled($loop->last) aria-label="Bajar bloque {{ $indice + 1 }}">Bajar</button>
                        <button type="button" wire:click="quitarBloque('{{ $bloque['id'] }}')" aria-label="Quitar bloque {{ $indice + 1 }}">Quitar bloque</button>
                    </div>
                    <label class="lab-admin-field">Título (opcional)<input wire:model="bloques.{{ $indice }}.titulo" maxlength="180"></label>
                    <label class="lab-admin-field">Texto (opcional)<textarea wire:model="bloques.{{ $indice }}.texto" rows="5" maxlength="20000"></textarea></label>
                    @error('bloques.'.$indice.'.texto')<p class="text-sm text-error" role="alert">{{ $message }}</p>@enderror
                    @php($fotoPublicada = collect($pagina['bloques'])->firstWhere('id', $bloque['id'])['foto'] ?? null)
                    @if($fotoPublicada['ruta'] ?? null)
                        <div class="lab-admin-preview"><x-laboratorio-foto :clave="'bloque-'.$bloque['id']" :foto="$fotoPublicada" /></div>
                        <label class="flex items-start gap-2 text-sm"><input type="checkbox" wire:model="quitarFotosBloques.{{ $bloque['id'] }}">Quitar fotografía publicada</label>
                    @endif
                    <label class="lab-admin-field">Fotografía (opcional)<input type="file" wire:model="fotosBloques.{{ $bloque['id'] }}" accept="image/jpeg,image/png,image/webp,image/avif"></label>
                    <label class="lab-admin-field">Descripción accesible de la fotografía<input wire:model="bloques.{{ $indice }}.alt" maxlength="180" required></label>
                </fieldset>
            @endforeach
            <button type="button" wire:click="agregarBloque" @disabled(count($bloques) >= 30) class="lab-admin-add">Añadir bloque</button>
        </section>
        <div class="flex items-center gap-4">
            <button type="submit" wire:loading.attr="disabled" class="rounded-lg bg-science-blue px-5 py-3 text-sm font-semibold text-white">Guardar y publicar</button>
            <span wire:loading role="status" class="text-sm text-text-secondary">Guardando contenido y fotografías…</span>
        </div>
    </form>
</div>
