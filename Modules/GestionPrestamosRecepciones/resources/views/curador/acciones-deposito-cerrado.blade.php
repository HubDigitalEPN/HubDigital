<flux:dropdown>
    <flux:button size="sm" variant="ghost" icon:trailing="chevron-down" aria-label="Acciones de {{ $solicitud->numero }}">Acciones</flux:button>
    <flux:menu>
        <flux:menu.item icon="eye" href="{{ route('prestamos.curador.deposito.revisar', $solicitud->id) }}" wire:navigate>Ver detalles</flux:menu.item>
        <flux:menu.item icon="arrow-down-tray" href="{{ route('prestamos.deposito.acta-recepcion', ['id' => $solicitud->id, 'descargar' => 1]) }}">Acta firmada</flux:menu.item>
    </flux:menu>
</flux:dropdown>
