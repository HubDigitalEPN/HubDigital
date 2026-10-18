<?php

declare(strict_types=1);

use App\Enums\RolUsuario;
use App\Models\User;
use Livewire\Livewire;
use Modules\CatalogoPublico\Presentation\Http\Controllers\PortalCatalogo;
use Modules\CatalogoPublico\Presentation\Http\Controllers\RegistrosTaxonExplorador;

uses(Tests\DatabaseFeatureTestCase::class);
uses(Tests\Concerns\ColeccionPortalAislada::class);

test('sólo administración y curaduría ven las funciones operativas del catálogo', function (?string $rol): void {
    if ($rol !== null) $this->actingAs(User::factory()->create(['rol' => $rol]));
    $autorizado = in_array($rol, ['ADMIN', 'CURADOR'], true);
    $pagina = $this->get(route('portal.catalogo', ['vista' => 'mapa']))->assertOk();
    foreach (['Ir al panel del curador', 'wire:click="descargarAnalisis', 'x-on:click="pdf()"', 'x-on:click="verRegistros(menuNodo)"'] as $control) {
        if ($autorizado) $pagina->assertSee($control, false);
        else $pagina->assertDontSee($control, false);
    }
    $pagina->assertDontSee('Datos originales por revisar')->assertDontSee('Consultar datos originales de la selección');
    if ($autorizado) $pagina->assertHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, no-store, private');
})->with([null, 'ADMIN', 'CURADOR', 'RECEPTOR', 'DEPOSITANTE', 'PRESTAMISTA']);

test('un visitante o un perfil ajeno a curaduría no puede invocar registros ni descargas directamente', function (?string $rol): void {
    if ($rol !== null) $this->actingAs(User::factory()->create(['rol' => $rol]));
    foreach ([['descargarResultados'], ['descargarAnalisis', 'mapa'], ['descargarListaEspecies'], ['descargarDatos'], ['abrirCelda', -.25, -78.5], ['abrirFichaRegistro', (string) \Illuminate\Support\Str::uuid()], ['cambiarVista', 'registros']] as $accion) {
        Livewire::withQueryParams(['vista' => 'mapa'])->test(PortalCatalogo::class)->call(...$accion)->assertForbidden();
    }
    Livewire::test(RegistrosTaxonExplorador::class)->assertForbidden();
    Livewire::withQueryParams(['vista' => 'registros'])->test(PortalCatalogo::class)->assertSet('vista', 'mapa');
    $this->getJson(route('portal.lista-especies'))->assertStatus($rol === null ? 401 : 403);
})->with([null, 'RECEPTOR', 'DEPOSITANTE', 'PRESTAMISTA']);

test('un curador sin verificar tampoco habilita operaciones del catálogo', function (): void {
    $this->actingAs(User::factory()->curador()->unverified()->create());
    Livewire::withQueryParams(['vista' => 'mapa'])->test(PortalCatalogo::class)->call('descargarAnalisis', 'mapa')->assertForbidden();
    $this->getJson(route('portal.lista-especies'))->assertForbidden();
});

test('login dirige al mapa sólo a curaduría y administración aunque existiera otro destino', function (string $rol): void {
    $usuario = User::factory()->create(['rol' => $rol]);
    $this->withSession(['url.intended' => route('portal.catalogo', ['vista' => 'mapa'])])
        ->post(route('login.store'), ['email' => $usuario->email, 'password' => 'password'])
        ->assertRedirect(in_array($rol, ['ADMIN', 'CURADOR'], true)
            ? route('portal.catalogo', ['vista' => 'mapa'], absolute: false)
            : route('dashboard', absolute: false));
    $this->assertAuthenticatedAs($usuario);
})->with(array_map(fn (RolUsuario $rol): string => $rol->value, RolUsuario::cases()));

test('el chat adapta los enlaces de registros al permiso y conserva los filtros de la selección', function (?string $rol): void {
    if ($rol !== null) $this->actingAs(User::factory()->create(['rol' => $rol]));
    $autorizado = in_array($rol, ['ADMIN', 'CURADOR'], true);
    $chat = Livewire::test(\Modules\CatalogoPublico\Presentation\Http\Controllers\ChatBotWidget::class)
        ->set('pregunta', '¿Cómo descargo los resultados filtrados en CSV?')
        ->call('enviar', ['fprov' => 'Pichincha']);
    $chat->assertViewHas('mensajesVisibles', function (array $mensajes) use ($autorizado): bool {
        $opcion = $mensajes[1]['opciones'][0];
        parse_str((string) parse_url($opcion['url'], PHP_URL_QUERY), $parametros);
        return ($parametros['fprov'] ?? null) === 'Pichincha'
            && ($parametros['vista'] ?? null) === ($autorizado ? 'registros' : 'mapa')
            && $opcion['label'] === ($autorizado ? 'Abrir registros filtrados' : 'Explorar selección en el mapa');
    })->assertSee('cuenta verificada de administración o curaduría');
    $documento = new DOMDocument;
    @$documento->loadHTML('<?xml encoding="UTF-8">'.$chat->html());
    $dom = new DOMXPath($documento);
    expect($dom->query('//a[normalize-space(.)="Abrir registros filtrados"]')->length)->toBe($autorizado ? 1 : 0)
        ->and($dom->query('//a[normalize-space(.)="Explorar selección en el mapa"]')->length)->toBe($autorizado ? 0 : 1);
})->with([null, 'ADMIN', 'CURADOR', 'RECEPTOR', 'DEPOSITANTE', 'PRESTAMISTA']);

test('las sugerencias del chat sólo ofrecen ver registros a curaduría', function (bool $autorizado): void {
    if ($autorizado) $this->actingAs(User::factory()->curador()->create());
    Livewire::test(\Modules\CatalogoPublico\Presentation\Http\Controllers\ChatBotWidget::class)
        ->set('pregunta', 'ke bichos ai aki')->call('enviar')
        ->assertViewHas('mensajesVisibles', function (array $mensajes) use ($autorizado): bool {
            $etiquetas = array_column($mensajes[1]['opciones'], 'label');
            return in_array('Ver registros', $etiquetas, true) === $autorizado
                && in_array('Contar especies', $etiquetas, true);
        });
})->with([false, true]);
