@if(session('foto-editorial-guardada'))<p data-hub-feedback="success" role="status">{{ session('foto-editorial-guardada') }}</p>@endif
@php($esLogoSistema = str_starts_with($fotoEditorialId ?? '', 'logo-sistema-'))
<flux:modal wire:model="fotoEditorialAbierta" aria-labelledby="foto-editorial-titulo" class="w-full max-w-xl">
    <form wire:submit="guardarFotoEditorial" class="space-y-4" data-copy-preserve>
        <h2 id="foto-editorial-titulo" class="hub-section-title">{{ $esLogoSistema ? 'Cambiar logo' : 'Cambiar fotografía' }}</h2>
        <x-editor-foto-input id="foto-editorial-archivo" modelo="fotoEditorial"
            :etiqueta="$esLogoSistema ? 'Logo' : 'Fotografía'"
            :accion="$esLogoSistema ? 'Elegir imagen' : 'Elegir fotografía'"
            :sin-archivo="$esLogoSistema ? 'Ninguna imagen seleccionada' : 'Ninguna foto seleccionada'" />
        <p wire:loading wire:target="fotoEditorial" role="status">{{ $esLogoSistema ? 'Cargando imagen…' : 'Cargando fotografía…' }}</p>
        @error('fotoEditorial')<p role="alert" class="text-error">{{ $message }}</p>@enderror
        <flux:textarea wire:model="fotoEditorialAlt" :label="$esLogoSistema ? 'Descripción accesible del logo' : 'Descripción accesible de la fotografía'" rows="2" maxlength="500"/>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="restaurarFotoEditorial">{{ $esLogoSistema ? 'Restaurar logo original' : 'Restaurar fotografía original' }}</label>
        <div class="flex justify-end gap-3"><flux:button type="button" wire:click="$set('fotoEditorialAbierta', false)">Cancelar</flux:button><flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="fotoEditorial,guardarFotoEditorial">Guardar y publicar</flux:button></div>
    </form>
</flux:modal>
