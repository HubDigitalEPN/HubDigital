<!DOCTYPE html>
<html lang="es">
    <head>
        @include('partials.head')
        <title>Espécimen no encontrado</title>
    </head>
    <body class="min-h-screen bg-bg-main">
        <main class="mx-auto flex min-h-screen w-full max-w-md flex-col items-center justify-center p-4 sm:p-6">
            <div class="w-full rounded-lg border border-border bg-surface p-6 text-center shadow-sm">
                <span class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-error/10">
                    <flux:icon name="exclamation-triangle" class="size-6 text-error" />
                </span>
                <h1 class="font-serif text-xl font-bold text-text-primary">Espécimen no encontrado</h1>
                <p class="mt-2 text-sm text-text-secondary">
                    El código QR escaneado no corresponde a ningún espécimen registrado en la colección.
                    Verifica que la etiqueta sea legible o solicita su regeneración al curador.
                </p>
                <flux:button href="{{ url('/') }}" variant="primary" icon="home" class="mt-4 w-full">
                    Ir al inicio
                </flux:button>
            </div>
        </main>

        @fluxScripts
    </body>
</html>
