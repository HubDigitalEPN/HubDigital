<flux:sidebar.group :heading="__('Divulgación')" class="grid">
    <flux:sidebar.item
        icon="table-cells"
        :href="route('divulgacion.index')"
        :current="request()->routeIs('divulgacion.index')"
        wire:navigate
    >
        {{ __('Tabla divulgada') }}
    </flux:sidebar.item>
    <flux:sidebar.item
        icon="cloud-arrow-up"
        :href="route('divulgacion.sincronizar')"
        :current="request()->routeIs('divulgacion.sincronizar')"
        wire:navigate
    >
        {{ __('Sincronización') }}
    </flux:sidebar.item>
</flux:sidebar.group>
