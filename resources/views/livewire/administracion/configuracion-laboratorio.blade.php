<div class="hub-workspace mx-auto max-w-7xl space-y-5 p-3 sm:p-5">
    <header class="hub-page-header">
        <div><h1 class="hub-page-title">Página El Laboratorio</h1><p class="mt-2 text-sm text-text-secondary">Edita la presentación, los perfiles y cada fotografía de la página pública.</p></div>
        <a href="{{ route('laboratorio') }}" target="_blank" rel="noopener" class="text-sm font-semibold text-science-blue underline">Ver página pública</a>
    </header>
    @if(session('laboratorio-guardado'))<p role="status" data-hub-feedback="success" class="hub-panel p-4 text-success">{{ session('laboratorio-guardado') }}</p>@endif
    @if($errors->any())<div role="alert" data-hub-feedback="error" class="hub-panel p-4 text-error"><p>No se publicaron los cambios:</p><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form wire:submit="guardar" class="space-y-5">
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
                            <textarea wire:model="textos.{{ $clave }}" rows="{{ $clave === 'principal' ? 7 : 3 }}" maxlength="5000" required></textarea>
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
        <div class="flex items-center gap-4">
            <button type="submit" wire:loading.attr="disabled" class="rounded-lg bg-science-blue px-5 py-3 text-sm font-semibold text-white">Guardar y publicar</button>
            <span wire:loading role="status" class="text-sm text-text-secondary">Guardando contenido y fotografías…</span>
        </div>
    </form>
</div>
