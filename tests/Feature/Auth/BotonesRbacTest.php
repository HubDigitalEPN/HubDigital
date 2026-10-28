<?php

use App\Enums\RolUsuario;
use App\Livewire\Administracion\GestionBotones;
use App\Livewire\Administracion\GestionUsuarios;
use App\Livewire\SelectorRolActivo;
use App\Models\User;
use App\Support\BotonesSistema;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Modules\CatalogoPublico\Presentation\Http\Controllers\PortalCatalogo;

test('administración combina depositante y admin sin alterar el perfil principal ni permitir autoasignación', function (): void {
    $admin = User::factory()->administrador()->create();
    $persona = User::factory()->depositante()->create(['email' => 'investigador@example.org']);
    Livewire::actingAs($admin)->test(GestionUsuarios::class)->call('editar', $persona->id)
        ->set('edicionRoles', ['DEPOSITANTE', 'ADMIN'])->set('edicionRol', 'DEPOSITANTE')->call('actualizar')->assertHasNoErrors();
    $persona->refresh()->load('roles');
    expect($persona->rolesAsignados()->all())->toContain(RolUsuario::DEPOSITANTE, RolUsuario::ADMIN)
        ->and($persona->rol)->toBe(RolUsuario::DEPOSITANTE);
    $portal = $this->actingAs($persona)->get(route('portal.catalogo', ['vista' => 'mapa']))->assertOk();
    $documento = new DOMDocument;
    @$documento->loadHTML('<?xml encoding="UTF-8">'.$portal->getContent());
    $dom = new DOMXPath($documento);
    expect($dom->query('//nav[@id="menu-portal-movil"]//summary[normalize-space(.)="Cambiar perfil"]')->length)->toBe(1);
    $this->actingAs($persona)->get(route('dashboard'))->assertOk();
    Livewire::actingAs($persona)->test(SelectorRolActivo::class)->call('cambiar', 'ADMIN')->assertRedirect(route('dashboard'));
    expect(session('rol_activo'))->toBe('ADMIN');
    Livewire::actingAs($persona)->test(SelectorRolActivo::class)->call('cambiar', 'CURADOR');
    expect(session('rol_activo'))->toBe('ADMIN')->and($persona->tieneRol(RolUsuario::CURADOR))->toBeFalse();
    Livewire::actingAs(User::factory()->depositante()->create())->test(GestionUsuarios::class)->assertForbidden();
});

test('el ayudante accede a descargas curatoriales y no administra permisos ni usuarios', function (): void {
    $ayudante = User::factory()->create(['rol' => RolUsuario::AYUDANTE_CURADOR]);
    $this->actingAs($ayudante)->get(route('dashboard'))->assertOk();
    $this->get(route('admin.botones'))->assertForbidden();
    $this->get(route('admin.usuarios'))->assertForbidden();
    expect(BotonesSistema::permitido('catalogo.completo'))->toBeTrue();
    Livewire::actingAs($ayudante)->test(GestionBotones::class)->assertForbidden();
});

test('la matriz conserva permisos separados de ver y usar y bloquea HTTP y Livewire tras revocar', function (): void {
    $admin = User::factory()->administrador()->create();
    $curador = User::factory()->curador()->create();
    Livewire::actingAs($admin)->test(GestionBotones::class)
        ->set('permisos.catalogo_completo.CURADOR.usar', false)->call('guardar')->assertHasNoErrors();
    $this->actingAs($curador);
    expect(BotonesSistema::visible('catalogo.completo'))->toBeTrue()->and(BotonesSistema::permitido('catalogo.completo'))->toBeFalse();
    $this->getJson(route('portal.descarga', ['exportacion' => 'datos']))->assertForbidden();
    Livewire::actingAs($curador)->test(PortalCatalogo::class)->call('descargarDatos')->assertForbidden();
    expect(BotonesSistema::permitido('catalogo.ipt'))->toBeTrue();
});

test('la matriz no puede divulgar el catálogo interno a perfiles externos ni guardar una edición obsoleta', function (): void {
    $admin = User::factory()->administrador()->create();
    $componente = Livewire::actingAs($admin)->test(GestionBotones::class);
    $componente->set('permisos.catalogo_completo.DEPOSITANTE.ver', true)->set('permisos.catalogo_completo.DEPOSITANTE.usar', true)
        ->call('guardar')->assertHasErrors('permisos');
    expect(DB::table('usuarios.permisos_botones')->count())->toBe(0);
    $primero = Livewire::actingAs($admin)->test(GestionBotones::class);
    $segundo = Livewire::actingAs($admin)->test(GestionBotones::class);
    $primero->call('guardar')->assertHasNoErrors();
    $segundo->call('guardar')->assertHasErrors('permisos');
    $admin->roles()->delete();
    $primero->call('guardar')->assertForbidden();
});

test('un perfil activo depositante no hereda los botones admin y una membresía revocada deja de servir', function (): void {
    $persona = User::factory()->conRoles(RolUsuario::DEPOSITANTE, RolUsuario::ADMIN)->create();
    $this->actingAs($persona);
    $persona->activarRol(RolUsuario::DEPOSITANTE);
    expect(BotonesSistema::permitido('catalogo.completo'))->toBeFalse();
    expect(\App\Support\DescargasCatalogo::equipoActivo())->toBeFalse();
    $persona->activarRol(RolUsuario::ADMIN);
    expect(BotonesSistema::permitido('catalogo.completo'))->toBeTrue();
    expect(\App\Support\DescargasCatalogo::equipoActivo())->toBeTrue();
    $persona->roles()->where('rol', 'ADMIN')->delete();
    expect(BotonesSistema::permitido('catalogo.completo'))->toBeFalse();
    expect($persona->nombreSaludo())->toBe(ucfirst(strtolower(explode(' ', $persona->first_name)[0])));
});

test('el comando de asignación agrega membresías sin crear cuentas ni elevarlas con valores desconocidos', function (): void {
    $persona = User::factory()->depositante()->create();
    $this->artisan('usuarios:asignar-perfiles', ['email' => $persona->email, 'roles' => ['ADMIN']])->assertSuccessful();
    expect($persona->fresh()->rolesAsignados()->all())->toContain(RolUsuario::DEPOSITANTE, RolUsuario::ADMIN);
    $this->artisan('usuarios:asignar-perfiles', ['email' => $persona->email, 'roles' => ['CURADOR', 'INVENTADO']])->assertFailed();
    expect($persona->fresh()->tieneRol(RolUsuario::CURADOR))->toBeFalse();
    $this->artisan('usuarios:asignar-perfiles', ['email' => 'ausente@example.org', 'roles' => ['ADMIN']])->assertFailed();
});
