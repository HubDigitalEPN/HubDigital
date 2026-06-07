<!DOCTYPE html>
<html lang="es">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen bg-bg-main">

<header class="bg-surface border-b border-border shadow-sm">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 items-center justify-between gap-4">
            <a href="{{ route('portal.catalogo') }}" class="flex min-w-0 items-center">
                <img src="/images/logo-DB.jpg" alt="Departamento de Biología EPN" class="h-10 w-auto object-contain" />
            </a>

            <nav class="hidden items-center gap-6 md:flex">
                <a href="#" class="text-sm text-text-secondary transition-colors hover:text-text-primary">Acerca de</a>
            </nav>
        </div>
    </div>
</header>

{{ $slot }}

@fluxScripts
</body>
</html>
