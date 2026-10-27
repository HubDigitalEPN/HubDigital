@props(['editar' => false])
<div class="sm:col-span-2 border-t border-border pt-3">
    <h3 class="text-sm font-semibold text-text-primary">Atribuciones</h3>
    <p class="mt-1 text-xs text-text-secondary">Completa los datos conocidos. Los campos vacíos se mostrarán como «No informado».</p>
</div>
<flux:input wire:model="photographer" label="Photographer" placeholder="Nombre completo del fotógrafo" maxlength="255" />
<flux:input wire:model="license" label="License" placeholder="Licencia indicada por el titular" maxlength="255" />
<flux:input wire:model="licenseHolder" label="License holder" maxlength="255" />
<flux:input wire:model="licenseInstitution" label="License institution" maxlength="255" />
<flux:input wire:model="contact" label="Contact" placeholder="Correo o enlace de contacto público" maxlength="255" />
@if($editar)<flux:input wire:model="viewLabel" label="Tipo de vista" placeholder="Dorsal view" maxlength="80" />@endif
