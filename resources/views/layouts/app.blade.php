<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main>
        <x-app-breadcrumbs />
        {{ $slot }}
        @unless(request()->routeIs('admin.contenidos'))
            <div class="mt-8"><x-platform-footer /></div>
        @endunless
    </flux:main>
</x-layouts::app.sidebar>
