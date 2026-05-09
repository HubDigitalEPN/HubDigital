@props(['field', 'label', 'checked' => true, 'sensible' => false])

<div {{ $attributes->merge(['class' => 'flex items-center justify-between py-2 px-3 rounded-lg hover:bg-bg-main transition-colors']) }}>
    <div class="flex items-center gap-2 min-w-0">
        @if($sensible)
            <flux:icon name="eye-slash" class="size-4 text-warning shrink-0" />
        @else
            <flux:icon name="eye" class="size-4 text-text-secondary shrink-0" />
        @endif
        <span class="text-sm text-text-primary truncate">{{ $label }}</span>
        @if($sensible)
            <span class="text-xs text-warning shrink-0">sensible</span>
        @endif
    </div>
    <flux:switch
        wire:model.live="{{ $field }}"
        :checked="$checked"
    />
</div>
