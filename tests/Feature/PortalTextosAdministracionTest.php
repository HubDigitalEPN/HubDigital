<?php

declare(strict_types=1);

use App\Livewire\Administracion\ConfiguracionTextosPortal;
use App\Models\User;
use App\Support\PortalCopy;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(Tests\DatabaseFeatureTestCase::class);

afterEach(fn () => PortalCopy::forget());

test('curadores y administradores guardan y restauran el contenido del portal con autoría', function (string $rol): void {
    $usuario = User::factory()->{$rol}()->create();
    $panel = Livewire::actingAs($usuario)->test(ConfiguracionTextosPortal::class)
        ->set('textos.inicio.hero_titulo', 'Colección científica editada')->call('guardar')->assertHasNoErrors();
    expect(PortalCopy::text('inicio.hero_titulo'))->toBe('Colección científica editada');
    expect(DB::table('usuarios.textos_portal')->where('clave', 'inicio.hero_titulo')->value('updated_by'))->toBe($usuario->id);
    $this->get(route('home'))->assertSee('Colección científica editada')->assertDontSee('Consultar el catálogo digital');
    $panel->call('restaurar', 'inicio', 'hero_titulo')->call('guardar')->assertHasNoErrors();
    expect(PortalCopy::text('inicio.hero_titulo'))->toBe(config('portal-copy.inicio.hero_titulo'));
})->with(['curador', 'administrador']);

test('el contenido se escapa y una validación fallida conserva los textos publicados', function (): void {
    $curador = User::factory()->curador()->create();
    $panel = Livewire::actingAs($curador)->test(ConfiguracionTextosPortal::class)
        ->set('textos.inicio.hero_titulo', '<script>alert(1)</script>')->call('guardar')->assertHasNoErrors();
    $html = $this->get(route('home'))->assertOk()->getContent();
    expect($html)->toContain('&lt;script&gt;alert(1)&lt;/script&gt;')->not->toContain('<script>alert(1)</script>');
    $panel->set('textos.contacto.email', 'correo-invalido')->call('guardar')->assertHasErrors(['textos.contacto.email']);
    expect(PortalCopy::text('contacto.email'))->toBe(config('portal-copy.contacto.email'));
});

test('visitantes y depositantes no pueden abrir ni modificar el editor del portal', function (): void {
    $this->get(route('admin.textos-portal'))->assertRedirect(route('login'));
    Livewire::test(ConfiguracionTextosPortal::class)->assertForbidden();
    $depositante = User::factory()->depositante()->create();
    $this->actingAs($depositante)->get(route('admin.textos-portal'))->assertForbidden();
    Livewire::actingAs($depositante)->test(ConfiguracionTextosPortal::class)->assertForbidden();
});
