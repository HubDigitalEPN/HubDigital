<?php

declare(strict_types=1);

use App\Enums\RolUsuario;
use App\Models\User;
use Livewire\Livewire;
use Modules\CatalogoPublico\Presentation\Http\Controllers\PortalCatalogo;
use Modules\CatalogoPublico\Presentation\Http\Controllers\RegistrosTaxonExplorador;

uses(Tests\DatabaseFeatureTestCase::class);
uses(Tests\Concerns\ColeccionPortalAislada::class);

test('todas las personas consultan registros y sólo las cuentas habilitadas ven las descargas', function (?string $rol): void {
    if ($rol !== null) $this->actingAs(User::factory()->create(['rol' => $rol]));
    $pagina = $this->get(route('portal.catalogo', ['vista' => 'mapa']))->assertOk();
    $documento = new DOMDocument;
    @$documento->loadHTML('<?xml encoding="UTF-8">'.$pagina->getContent());
    $dom = new DOMXPath($documento);
    $salidas = $dom->query('//header//form[@method="POST" and @action="'.route('logout').'"]');
    expect($salidas->length)->toBe($rol === null ? 0 : 2);
    foreach ($salidas as $salida) {
        expect($dom->query('.//input[@name="_token"]', $salida)->length)->toBe(1)
            ->and(trim($dom->query('.//button[@type="submit"]', $salida)->item(0)->textContent))->toBe('Cerrar sesión');
    }
    $pagina->assertSee('x-on:click="verRegistros(menuNodo)"', false)->assertSee('Ver en el mapa');
    if (in_array($rol, ['ADMIN', 'CURADOR'], true)) $pagina->assertSee('Ir al panel del curador');
    else $pagina->assertDontSee('Ir al panel del curador');
    foreach (['data-catalogo-descarga', 'x-on:click="pdf()"'] as $control) {
        if ($rol !== null) $pagina->assertSee($control, false);
        else $pagina->assertDontSee($control, false);
    }
    Livewire::withQueryParams(['vista' => 'registros'])->test(PortalCatalogo::class)->assertSet('vista', 'registros');
    Livewire::test(RegistrosTaxonExplorador::class)->assertOk();
})->with([null, 'ADMIN', 'CURADOR', 'RECEPTOR', 'DEPOSITANTE', 'PRESTAMISTA']);

test('el flag deshabilitado bloquea todas las descargas externas incluso por HTTP o Livewire', function (?string $rol): void {
    \Illuminate\Support\Facades\DB::table('usuarios.configuracion_catalogo')->where('clave', 'descargas_autoservicio')->update(['habilitado' => false]);
    if ($rol !== null) $this->actingAs(User::factory()->create(['rol' => $rol]));
    foreach ([['descargarResultados'], ['descargarAnalisis', 'mapa'], ['descargarListaEspecies'], ['descargarDatos']] as $accion) {
        Livewire::withQueryParams(['vista' => 'mapa'])->test(PortalCatalogo::class)->call(...$accion)->assertForbidden();
    }
    Livewire::withQueryParams(['vista' => 'mapa'])->test(PortalCatalogo::class)->call('cambiarVista', 'registros')->assertSet('vista', 'registros');
    $this->getJson(route('portal.lista-especies'))->assertStatus($rol === null ? 401 : 403);
    foreach (['resultados', 'datos', 'ipt'] as $perfil) $this->getJson(route('portal.descarga', ['exportacion' => $perfil]))->assertStatus($rol === null ? 401 : 403);
})->with([null, 'RECEPTOR', 'DEPOSITANTE', 'PRESTAMISTA']);

test('el permiso de descarga se aplica a cuentas verificadas y curaduría conserva su acceso', function (): void {
    $usuario = User::factory()->depositante()->create();
    expect(\App\Support\DescargasCatalogo::permitidas($usuario))->toBeTrue();
    \Illuminate\Support\Facades\DB::table('usuarios.configuracion_catalogo')->where('clave', 'descargas_autoservicio')->update(['habilitado' => false]);
    expect(\App\Support\DescargasCatalogo::permitidas($usuario))->toBeFalse()
        ->and(\App\Support\DescargasCatalogo::permitidas(User::factory()->curador()->create()))->toBeTrue()
        ->and(\App\Support\DescargasCatalogo::permitidas(User::factory()->depositante()->unverified()->create()))->toBeFalse();
});

test('un curador sin verificar tampoco habilita operaciones del catálogo', function (): void {
    $this->actingAs(User::factory()->curador()->unverified()->create());
    Livewire::withQueryParams(['vista' => 'mapa'])->test(PortalCatalogo::class)->call('descargarAnalisis', 'mapa')->assertForbidden();
    $this->getJson(route('portal.lista-especies'))->assertForbidden();
    $this->getJson(route('portal.descarga', ['exportacion' => 'resultados']))->assertForbidden();
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

test('el acceso JSON conserva el destino por perfil y renueva el token de sesión sin redirección HTTP', function (string $rol): void {
    $usuario = User::factory()->create(['rol' => $rol]);
    $this->withSession(['url.intended' => 'https://otro-sitio.example/'])
        ->postJson(route('login.store'), ['email' => $usuario->email, 'password' => 'password'])
        ->assertOk()->assertJsonPath('two_factor', false)
        ->assertJsonPath('redirect', in_array($rol, ['ADMIN', 'CURADOR'], true)
            ? route('portal.catalogo', ['vista' => 'mapa']) : route('dashboard'))
        ->assertJsonPath('csrf', csrf_token())
        ->assertHeaderMissing('Location');
    $this->assertAuthenticatedAs($usuario);
})->with(['ADMIN', 'CURADOR', 'DEPOSITANTE']);

test('el chat adapta los enlaces de registros al permiso y conserva los filtros de la selección', function (?string $rol): void {
    if ($rol !== null) $this->actingAs(User::factory()->create(['rol' => $rol]));
    $autorizado = $rol !== null;
    $chat = Livewire::test(\Modules\CatalogoPublico\Presentation\Http\Controllers\ChatBotWidget::class)
        ->set('pregunta', '¿Cómo descargo los resultados filtrados en CSV?')
        ->call('enviar', ['fprov' => 'Pichincha']);
    $chat->assertViewHas('mensajesVisibles', function (array $mensajes) use ($autorizado): bool {
        $opcion = $mensajes[1]['opciones'][0];
        parse_str((string) parse_url($opcion['url'], PHP_URL_QUERY), $parametros);
        return ($parametros['fprov'] ?? null) === 'Pichincha'
            && ($parametros['vista'] ?? null) === 'registros'
            && $opcion['label'] === 'Abrir registros filtrados';
    })->assertSee('cuenta verificada');
    $documento = new DOMDocument;
    @$documento->loadHTML('<?xml encoding="UTF-8">'.$chat->html());
    $dom = new DOMXPath($documento);
    expect($dom->query('//a[normalize-space(.)="Abrir registros filtrados"]')->length)->toBe(1)
        ->and($dom->query('//a[normalize-space(.)="Explorar selección en el mapa"]')->length)->toBe(0);
})->with([null, 'ADMIN', 'CURADOR', 'RECEPTOR', 'DEPOSITANTE', 'PRESTAMISTA']);

test('las sugerencias del chat ofrecen la consulta de registros a todos los perfiles', function (bool $autorizado): void {
    if ($autorizado) $this->actingAs(User::factory()->curador()->create());
    Livewire::test(\Modules\CatalogoPublico\Presentation\Http\Controllers\ChatBotWidget::class)
        ->set('pregunta', 'ke bichos ai aki')->call('enviar')
        ->assertViewHas('mensajesVisibles', function (array $mensajes) use ($autorizado): bool {
            $etiquetas = array_column($mensajes[1]['opciones'], 'label');
            return in_array('Ver registros', $etiquetas, true)
                && in_array('Contar especies', $etiquetas, true);
        });
})->with([false, true]);
