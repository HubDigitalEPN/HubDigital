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
    if (in_array($rol, ['ADMIN', 'CURADOR', 'AYUDANTE_CURADOR'], true)) $pagina->assertSee('Ir al panel del curador');
    else $pagina->assertDontSee('Ir al panel del curador');
    foreach (['data-catalogo-descarga', 'x-on:click="pdf()"'] as $control) {
        $pagina->assertSee($control, false);
    }
    Livewire::withQueryParams(['vista' => 'registros'])->test(PortalCatalogo::class)->assertSet('vista', 'registros');
    Livewire::test(RegistrosTaxonExplorador::class)->assertOk();
})->with([null, 'ADMIN', 'CURADOR', 'AYUDANTE_CURADOR', 'DEPOSITANTE', 'PASANTE']);

test('el flag administrativo no impide descargas públicas y el catálogo completo sigue protegido', function (?string $rol): void {
    \Illuminate\Support\Facades\DB::table('usuarios.configuracion_catalogo')->where('clave', 'descargas_autoservicio')->update(['habilitado' => false]);
    if ($rol !== null) $this->actingAs(User::factory()->create(['rol' => $rol]));
    expect(\App\Support\DescargasCatalogo::permitidas(boton: 'catalogo.resultados'))->toBeTrue()
        ->and(\App\Support\DescargasCatalogo::permitidas(boton: 'catalogo.analisis'))->toBeTrue();
    Livewire::withQueryParams(['vista' => 'mapa'])->test(PortalCatalogo::class)->call('descargarDatos')->assertForbidden();
    Livewire::withQueryParams(['vista' => 'mapa'])->test(PortalCatalogo::class)->call('cambiarVista', 'registros')->assertSet('vista', 'registros');
    $this->getJson(route('portal.lista-especies'))->assertOk();
    $this->getJson(route('portal.descarga', ['exportacion' => 'resultados']))->assertOk();
    foreach (['datos', 'ipt'] as $perfil) $this->getJson(route('portal.descarga', ['exportacion' => $perfil]))->assertForbidden();
})->with([null, 'PASANTE', 'DEPOSITANTE']);

test('las descargas públicas y las exportaciones curatoriales conservan sus permisos separados', function (): void {
    $usuario = User::factory()->depositante()->create();
    \Illuminate\Support\Facades\DB::table('usuarios.configuracion_catalogo')->where('clave', 'descargas_autoservicio')->update(['habilitado' => false]);
    expect(\App\Support\DescargasCatalogo::permitidas($usuario))->toBeTrue()
        ->and(\App\Support\DescargasCatalogo::permitidas($usuario, 'catalogo.completo'))->toBeFalse()
        ->and(\App\Support\DescargasCatalogo::permitidas(User::factory()->curador()->create(), 'catalogo.completo'))->toBeTrue()
        ->and(\App\Support\DescargasCatalogo::permitidas(User::factory()->depositante()->unverified()->create()))->toBeTrue();
});

test('una cuenta sin verificar conserva la consulta pública pero no operaciones internas', function (): void {
    $this->actingAs(User::factory()->curador()->unverified()->create());
    $this->getJson(route('portal.lista-especies'))->assertOk();
    $this->getJson(route('portal.descarga', ['exportacion' => 'resultados']))->assertOk();
    $this->getJson(route('portal.descarga', ['exportacion' => 'datos']))->assertForbidden();
});

test('login dirige al mapa sólo a curaduría y administración aunque existiera otro destino', function (string $rol): void {
    $usuario = User::factory()->create(['rol' => $rol]);
    $this->withSession(['url.intended' => route('portal.catalogo', ['vista' => 'mapa'])])
        ->post(route('login.store'), ['email' => $usuario->email, 'password' => 'password'])
        ->assertRedirect(in_array($rol, ['ADMIN', 'CURADOR', 'AYUDANTE_CURADOR'], true)
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
    })->assertSee('públic');
    $documento = new DOMDocument;
    @$documento->loadHTML('<?xml encoding="UTF-8">'.$chat->html());
    $dom = new DOMXPath($documento);
    expect($dom->query('//a[normalize-space(.)="Abrir registros filtrados"]')->length)->toBe(1)
        ->and($dom->query('//a[normalize-space(.)="Explorar selección en el mapa"]')->length)->toBe(0);
})->with([null, 'ADMIN', 'CURADOR', 'AYUDANTE_CURADOR', 'DEPOSITANTE', 'PASANTE']);

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
