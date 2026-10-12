<?php

declare(strict_types=1);

use App\Models\User;

uses(Tests\DatabaseFeatureTestCase::class);

test('sólo las aperturas públicas anónimas autorizan guardar el documento renderizado en la PWA', function (): void {
    $this->get('/')->assertOk()->assertHeader('X-Portal-Public-Cache', 'document');
    $this->get('/portal/catalogo?vista=mapa')->assertOk()->assertHeader('X-Portal-Public-Cache', 'document');
    $this->get('/portal/catalogo')->assertOk()->assertHeader('X-Portal-Public-Cache', 'document');
    $this->get('/?otra=consulta')->assertOk()->assertHeaderMissing('X-Portal-Public-Cache');
    $this->get('/portal/catalogo?vista=registros')->assertOk()->assertHeaderMissing('X-Portal-Public-Cache');
    $this->actingAs(User::factory()->curador()->create())->get('/')->assertOk()->assertHeaderMissing('X-Portal-Public-Cache');
});

test('el arranque público entrega la sesión actual sin almacenamiento y rechaza scripts de otro sitio', function (): void {
    $respuesta = $this->get('/portal/pwa/arranque.js')->assertOk()->assertHeaderMissing('X-Portal-Public-Cache');
    expect($respuesta->headers->get('Cache-Control'))->toContain('no-store')->toContain('private');
    expect($respuesta->getContent())->toContain('meta[name="csrf-token"]')->toContain('setAttribute');
    $this->withHeader('Sec-Fetch-Site', 'cross-site')->get('/portal/pwa/arranque.js')->assertForbidden();
});

test('el portal descarga el arranque por adelantado y presenta el contenido antes de ejecutarlo', function (): void {
    $html = $this->get('/portal/catalogo?vista=mapa')->assertOk()->getContent();
    $preload = strpos($html, '<link rel="preload" href="'.route('portal.pwa-arranque').'" as="script">');
    $script = strpos($html, '<script src="'.route('portal.pwa-arranque').'"></script>');
    expect($preload)->not->toBeFalse();
    expect($script)->not->toBeFalse();
    expect($preload)->toBeLessThan(strpos($html, '</head>'));
    expect($script)->toBeGreaterThan(strpos($html, '</main>'));
    expect($html)->toContain('atlas-dashboard')->not->toContain('wire:lazy');
});

test('los portales declaran su tema claro incluso para una cuenta autenticada', function (): void {
    foreach (['/', '/portal/catalogo?vista=mapa'] as $url) {
        $this->get($url)->assertOk()->assertSee('data-portal-theme="light"', false)
            ->assertSee('<meta name="color-scheme" content="light"', false)
            ->assertSee("document.documentElement.classList.remove('dark')", false);
    }
    $this->actingAs(User::factory()->curador()->create())->get('/')->assertOk()
        ->assertSee('data-portal-theme="light"', false)->assertDontSee('window.Flux.applyAppearance', false);
    $this->get('/settings/appearance')->assertOk()->assertDontSee('data-portal-theme="light"', false);
});
