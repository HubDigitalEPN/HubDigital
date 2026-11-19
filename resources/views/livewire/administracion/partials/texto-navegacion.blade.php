<flux:modal wire:model="textoNavegacionAbierto" aria-labelledby="texto-navegacion-titulo" class="w-full max-w-xl">
    <form wire:submit="guardarTextoNavegacion" class="space-y-4" data-copy-preserve>
        <h2 id="texto-navegacion-titulo" class="hub-section-title">Editar texto</h2>
        <flux:textarea wire:model="textoNavegacion" label="Texto que se publicará" rows="3" maxlength="5000"/>
        @error('textoActual')<p role="alert" class="text-error">{{ $message }}</p>@enderror
        <div class="flex justify-end gap-3"><flux:button type="button" wire:click="$set('textoNavegacionAbierto', false)">Cancelar</flux:button><flux:button type="submit" variant="primary">Guardar y publicar</flux:button></div>
    </form>
</flux:modal>
