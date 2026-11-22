<x-layouts::app.sidebar :title="$title ?? null">
    @php($pieAlFondo = request()->routeIs('admin.catalogos', 'admin.matrices', 'admin.usuarios', 'admin.botones', 'admin.menu-lateral'))
    <flux:main :class="$pieAlFondo ? 'flex flex-col' : ''">
        <x-app-breadcrumbs />
        {{ $slot }}
        @unless(request()->routeIs('admin.contenidos'))
            <div class="{{ $pieAlFondo ? 'mt-auto pt-4' : 'mt-8' }}"><x-platform-footer /></div>
        @endunless
    </flux:main>
</x-layouts::app.sidebar>
