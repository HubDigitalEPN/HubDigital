<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main>
        <x-app-breadcrumbs />
        {{ $slot }}
        <div class="mt-8"><x-platform-footer /></div>
    </flux:main>
</x-layouts::app.sidebar>
