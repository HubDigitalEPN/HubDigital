<div class="grid gap-3">
    @foreach ($roles as $rol)
        <button type="button" wire:click="cambiar('{{ $rol->value }}')"
            class="flex min-h-14 items-center justify-between rounded-lg border border-border bg-surface px-4 text-left text-sm text-text-primary transition hover:border-bio-green hover:bg-bg-main"
            @if($rol->value === $rolActivo->value) aria-current="true" @endif>
            <span>{{ $rol->etiqueta() }}</span>
            @if($rol->value === $rolActivo->value)<span class="text-xs font-semibold text-bio-green">Rol activo</span>@endif
        </button>
    @endforeach
    @if($rolActivable)
        <a href="{{ route('roles.activar', strtolower($rolActivable->value)) }}"
            class="flex min-h-14 items-center justify-between rounded-lg border border-dashed border-bio-green/50 px-4 text-sm font-medium text-bio-green transition hover:bg-bio-green/5">
            Activar rol de {{ $rolActivable->etiqueta() }}
            <span aria-hidden="true">+</span>
        </a>
    @endif
</div>
