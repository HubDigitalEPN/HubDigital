<?php

test('el menú móvil expone estado y devuelve el foco al cerrarse con escape', function (): void {
    $layout = file_get_contents(dirname(__DIR__, 2).'/resources/views/layouts/portal.blade.php');

    expect($layout)
        ->not->toBeFalse()
        ->toContain('x-ref="menuButton"')
        ->toContain("x-text=\"abierto ? @js(\$navegacion('cerrar_menu')) : @js(\$navegacion('abrir_menu'))\"")
        ->toContain(':aria-expanded="abierto.toString()"')
        ->toContain('aria-controls="menu-portal-movil"')
        ->toContain('$refs.menuButton.focus()');
});

test('el chat conserva controles táctiles y refluye cuando el zoom reduce el viewport', function (): void {
    $chat = file_get_contents(dirname(__DIR__, 2).'/Modules/CatalogoPublico/resources/views/livewire/chat-bot-widget.blade.php');

    expect($chat)
        ->not->toBeFalse()
        ->toContain('portal-chat-shell')
        ->toContain('h-[min(34rem,calc(100dvh-2rem))]')
        ->toContain('sm:h-[min(34rem,calc(100dvh-7rem))]')
        ->toContain('min-h-0 flex-1')
        ->toContain('x-on:keydown.escape.window')
        ->toContain('$refs.trigger.focus()')
        ->toContain('aria-label="Escribe tu pregunta al asistente"')
        ->toContain('required')
        ->toContain('flex size-11 shrink-0')
        ->toContain('min-h-11 min-w-0')
        ->toContain('aria-label="Abrir asistente HubDigital"');

    $styles = file_get_contents(dirname(__DIR__, 2).'/resources/css/app.css');

    expect($styles)
        ->not->toBeFalse()
        ->toContain('--chat-bottom: max(clamp(12px, 10dvh, 72px), env(safe-area-inset-bottom, 0px))')
        ->toContain('bottom: var(--chat-bottom)')
        ->toContain('height: min(34rem, calc(100dvh - var(--chat-bottom) - 16px))')
        ->toContain('max-height: calc(100dvh - var(--chat-bottom) - 16px)')
        ->toContain('width: clamp(44px, 7dvh, 56px)')
        ->toContain('height: clamp(44px, 7dvh, 56px)')
        ->toContain('@media (prefers-reduced-motion: reduce)');
});

test('el hero adapta su altura y tipografía al escritorio visible', function (): void {
    $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/portal-inicio.blade.php');
    $styles = file_get_contents(dirname(__DIR__, 2).'/resources/css/app.css');

    expect($view)
        ->not->toBeFalse()
        ->toContain('class="portal-hero-grid"')
        ->not->toContain('lg:min-h-[42rem]');

    expect($styles)
        ->not->toBeFalse()
        ->toContain('@media (min-width: 1024px)')
        ->toContain('min-height: min(42rem, calc(100svh - 4.75rem))')
        ->toContain('padding-block: clamp(1.5rem, 4vh, 3.5rem)')
        ->toContain('font-size: clamp(2.45rem, min(4.2vw, 7.5vh), 4.5rem)');
});
