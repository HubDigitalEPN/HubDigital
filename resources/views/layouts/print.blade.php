<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name') }}</title>
    @livewireStyles
    @livewireScriptConfig
    @vite(['resources/css/app.css', 'resources/css/portal.css', 'resources/js/app.js'])
</head>
<body class="bg-white antialiased">
    {{ $slot }}
</body>
</html>
