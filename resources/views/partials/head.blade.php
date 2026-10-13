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
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto+Slab:wght@600;700&family=Inter:wght@400;500;600&display=swap" />

@livewireScriptConfig
@vite(['resources/css/app.css', 'resources/css/portal.css', 'resources/js/app.js'])
@unless($portalClaro ?? false)
    @fluxAppearance
@endunless
