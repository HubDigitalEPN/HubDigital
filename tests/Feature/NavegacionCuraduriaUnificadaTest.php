<?php

declare(strict_types=1);

use App\Livewire\Administracion\ContenidosTextos;
use App\Livewire\Dashboard;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Modules\CatalogoPublico\Presentation\Http\Controllers\SincronizarEspecimenes;
use Modules\CatalogoPublico\Presentation\Http\Controllers\TablaEspecimenesDivulgados;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\EspecimenIndex;

uses(Tests\DatabaseFeatureTestCase::class);

beforeEach(fn () => $this->withoutExceptionHandling([Symfony\Component\HttpKernel\Exception\HttpException::class]));

test('el menú tiene un único acceso a cada función reorganizada y distingue las etapas del depósito', function (string $rol): void {
    $this->actingAs(User::factory()->{$rol}()->create());
    $html = $this->get(route('dashboard'))->assertOk()->getContent();
    $doc = new DOMDocument;
    @$doc->loadHTML('<?xml encoding="UTF-8">'.$html);
    $xpath = new DOMXPath($doc);
    foreach (['divulgacion.index', 'admin.contenidos', 'prestamos.curador.depositos', 'prestamos.receptor.depositos'] as $ruta) {
        expect($xpath->query('//nav[@data-flux-sidebar-nav]//ui-disclosure//a[@href="'.route($ruta).'"]')->length)->toBe(1);
    }
    $menu = $xpath->query('//nav[@data-flux-sidebar-nav]')->item(0)->textContent;
    expect($menu)->toContain('Publicación del catálogo', 'Contenidos y textos', 'Revisión documental y actas', 'Recepción física', 'Recordatorios de préstamos')
        ->not->toContain('Control de calidad', 'Centro de revisión', 'Centro de administración', 'Textos de todas las pantallas', 'Divulgar espécimenes');
    expect($xpath->query('//nav[@aria-label="Accesos principales"]//a[text()[contains(., "Buscar")]]')->length)->toBe(0);
    expect($xpath->query('//section[@aria-labelledby="coleccion-title"]//a[@href="'.route('inventario.taxonomia.localidades').'"]')->length)->toBe(1);
})->with(['curador', 'administrador']);

test('las rutas retiradas no conservan pantallas ni redirecciones', function (): void {
    $this->actingAs(User::factory()->administrador()->create());
    foreach (['/administracion', '/administracion/textos-portal', '/administracion/textos-depositos', '/administracion/textos-sistema', '/divulgacion/sincronizar', '/inventario/taxonomia/entidades-depositantes', '/inventario/taxonomia/revision', '/inventario/taxonomia/taxones/revision', '/inventario/taxonomia/localidades/revision', '/inventario/taxonomia/especimenes/duplicados', '/inventario/taxonomia/fechas/revision', '/inventario/taxonomia/muestras'] as $url) {
        $this->get($url)->assertNotFound();
    }
    foreach (['admin.centro', 'admin.textos-portal', 'admin.textos-depositos', 'admin.textos-sistema', 'divulgacion.sincronizar', 'inventario.taxonomia.revision'] as $nombre) {
        expect(Route::has($nombre))->toBeFalse();
    }
});

test('el editor único conserva los permisos de los editores incluidos', function (): void {
    $this->actingAs(User::factory()->curador()->create());
    $this->get(route('admin.contenidos'))->assertOk()->assertSeeLivewire(App\Livewire\Administracion\ConfiguracionTextosPortal::class)
        ->assertDontSee('Textos generales del sistema');
    foreach (['depositos', 'sistema'] as $seccion) {
        $this->get(route('admin.contenidos', compact('seccion')))->assertForbidden();
        Livewire::test(ContenidosTextos::class)->set('seccion', $seccion)->assertForbidden();
    }
    $this->actingAs(User::factory()->administrador()->create());
    $this->get(route('admin.contenidos', ['seccion' => 'depositos']))->assertOk()->assertSeeLivewire(App\Livewire\Administracion\ConfiguracionTextosWizard::class);
    $this->get(route('admin.contenidos', ['seccion' => 'sistema']))->assertOk()->assertSeeLivewire(App\Livewire\Administracion\ConfiguracionTextosSistema::class);
    $this->actingAs(User::factory()->depositante()->create())->get(route('admin.contenidos'))->assertForbidden();
});

test('publicación reúne pendientes y divulgados en una página sin cambiar sus componentes de negocio', function (): void {
    $this->actingAs(User::factory()->curador()->create());
    $this->get(route('divulgacion.index'))->assertOk()->assertSee('Publicación del catálogo')
        ->assertSeeLivewire(TablaEspecimenesDivulgados::class)->assertDontSeeLivewire(SincronizarEspecimenes::class)->assertDontSee('Revisión de datos');
    $this->get(route('divulgacion.index', ['vista' => 'pendientes']))->assertOk()
        ->assertSeeLivewire(SincronizarEspecimenes::class)->assertDontSeeLivewire(TablaEspecimenesDivulgados::class);
    $this->get(route('divulgacion.index', ['vista' => 'inexistente']))->assertUnprocessable();
    $this->actingAs(User::factory()->depositante()->create())->get(route('divulgacion.index'))->assertForbidden();
});

test('el resumen administrativo se integra en el dashboard y queda restringido al creador', function (): void {
    $this->actingAs(User::factory()->administrador()->create());
    Livewire::test(Dashboard::class)->assertViewHas('administracion', fn ($datos) => $datos['total'] === User::query()->count()
        && $datos['verificados'] === User::query()->whereNotNull('email_verified_at')->count()
        && $datos['pendientes'] === User::query()->whereNull('email_verified_at')->count())
        ->assertSee('Usuarios y perfiles')->assertSee('Ingresos aprobados');
    $this->actingAs(User::factory()->curador()->create());
    Livewire::test(Dashboard::class)->assertViewHas('administracion', null)->assertDontSee('Pendientes de verificar');
});

test('el catálogo no ofrece pasos de control de calidad ni recupera columnas retiradas de preferencias antiguas', function (): void {
    $this->actingAs(User::factory()->curador()->create());
    session(['inventario.especimenes.columnas.v3' => ['orden' => ['estadoRevision', 'motivoRevision', 'revisionNombreCientifico', 'codigoCatalogo'], 'visibles' => ['estadoRevision', 'motivoRevision', 'revisionNombreCientifico', 'codigoCatalogo']]]);
    Livewire::test(EspecimenIndex::class)->assertOk()->assertDontSee('Centro de revisión')->assertDontSee('Colas de control curatorial')
        ->assertDontSee('Estado de revisión')->assertDontSee('Motivo revisión contiene')
        ->assertViewHas('columnasRegistro', fn ($columnas) => !in_array('revision', array_column($columnas, 'grupo'), true));
});
