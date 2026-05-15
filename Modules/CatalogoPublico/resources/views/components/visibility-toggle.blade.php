@props(['field' => null, 'label', 'checked' => true, 'sensible' => false])

<button
    type="button"
    {{ $attributes->merge(['class' => 'flex w-full items-center justify-between gap-3 py-2 px-3 rounded-lg hover:bg-bg-main transition-colors cursor-pointer text-left']) }}
>
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
    <span @class([
        'relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition-colors duration-200',
        'bg-zinc-200 dark:bg-zinc-600' => !$checked,
        'bg-science-blue' => $checked,
    ])>
        <span @class([
            'inline-block size-3.5 rounded-full bg-white shadow-sm transition-transform duration-200',
            'translate-x-0.5' => !$checked,
            'translate-x-5' => $checked,
        ])></span>
    </span>
</button>
