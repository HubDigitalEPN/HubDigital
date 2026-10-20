<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="csrf-token" content="{{ csrf_token() }}" />
@if($portalClaro ?? false)
    <meta name="color-scheme" content="light" />
    <style>:root { color-scheme: only light; }</style>
    <script>document.documentElement.classList.remove('dark');</script>
@endif

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<link rel="manifest" href="/manifest.webmanifest">
<meta name="theme-color" content="#17375e">
<meta name="application-name" content="HubDigital EPN">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="HubDigital EPN">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Roboto+Slab:wght@600;700&family=Inter:wght@400;500;600&display=swap" onload="this.onload=null;this.rel='stylesheet'" />
<noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto+Slab:wght@600;700&family=Inter:wght@400;500;600&display=swap" /></noscript>
@if(config('services.turnstile.enabled') && request()->routeIs('login', 'register'))
    <link rel="preconnect" href="https://challenges.cloudflare.com">
    <script src="{{ asset('js/hub-turnstile.js') }}?v={{ filemtime(public_path('js/hub-turnstile.js')) }}"></script>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit&onload=hubTurnstileReady" async defer></script>
@endif

<script src="{{ asset('js/interactive-grid.js') }}?v={{ filemtime(public_path('js/interactive-grid.js')) }}"></script>
@livewireScriptConfig
<script src="{{ asset('js/lov-origen.js') }}?v={{ filemtime(public_path('js/lov-origen.js')) }}"></script>
@vite(['resources/css/app.css', 'resources/css/portal.css', 'resources/js/app.js'])
@unless($portalClaro ?? false)
    @fluxAppearance
@endunless

<link rel="stylesheet" href="{{ asset('css/formularios-apex.css') }}?v={{ filemtime(public_path('css/formularios-apex.css')) }}" />
<script src="{{ asset('js/formularios-apex.js') }}?v={{ filemtime(public_path('js/formularios-apex.js')) }}" defer></script>
<link rel="stylesheet" href="{{ asset('css/interactive-grid.css') }}?v={{ filemtime(public_path('css/interactive-grid.css')) }}" />
<link rel="stylesheet" href="{{ asset('css/platform-information.css') }}?v={{ filemtime(public_path('css/platform-information.css')) }}" />
<script src="{{ asset('js/platform-information.js') }}?v={{ filemtime(public_path('js/platform-information.js')) }}" defer></script>
