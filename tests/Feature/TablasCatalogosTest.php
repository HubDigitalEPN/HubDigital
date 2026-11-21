<?php

declare(strict_types=1);

use App\Livewire\Administracion\CargosCatalogo;
use App\Livewire\Administracion\FuentesRevocacionFirma;
use App\Livewire\Administracion\GruposAnimalesCatalogo;
use App\Livewire\Administracion\InstitucionesCatalogo;
use App\Livewire\Administracion\LocalidadesEcuadorCatalogo;
use App\Livewire\Administracion\TablasCatalogos;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\LocalidadIndex;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\TaxonIndex;

uses(Tests\DatabaseFeatureTestCase::class);

beforeEach(fn () => $this->withoutExceptionHandling([
    Symfony\Component\HttpKernel\Exception\HttpException::class,
    Illuminate\Auth\AuthenticationException::class,
]));

test('cada enlace anterior abre su tabla en la administración unificada', function (string $anterior, string $tabla, string $componente): void {
    $this->actingAs(User::factory()->administrador()->create());
    $nueva = route('admin.catalogos', ['tabla' => $tabla]);

    $this->get(route($anterior, ['tabla' => 'otra', 'q' => 'QA']))
        ->assertRedirect(route('admin.catalogos', ['tabla' => $tabla, 'q' => 'QA']));
    $this->get($nueva)->assertOk()->assertDontSeeLivewire($componente)
        ->assertSeeHtml('data-tabla-catalogo="'.$tabla.'"')->assertSeeHtml('data-registros-catalogos');
})->with([
    ['admin.instituciones', 'instituciones', InstitucionesCatalogo::class],
    ['admin.cargos', 'cargos', CargosCatalogo::class],
    ['admin.grupos-animales', 'grupos-animales', GruposAnimalesCatalogo::class],
    ['admin.fuentes-revocacion', 'fuentes-revocacion', FuentesRevocacionFirma::class],
    ['admin.localidades', 'localidades-ecuador', LocalidadesEcuadorCatalogo::class],
    ['inventario.taxonomia.localidades', 'localidades-coleccion', LocalidadIndex::class],
    ['inventario.taxonomia.taxones', 'taxones', TaxonIndex::class],
]);

test('la cuadrícula comparte la búsqueda y conserva los permisos de curaduría y administración', function (): void {
    $restringida = route('admin.catalogos', ['tabla' => 'localidades-ecuador']);
    $this->get(route('admin.catalogos'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->curador()->create());
    $cargo = DB::table('usuarios.cargos_catalogo')->insertGetId(['nombre' => 'QA Universo Cargo', 'activo' => true]);
    DB::table('usuarios.instituciones_catalogo')->insert(['nombre' => 'QA Universo Institución', 'activo' => true]);
    $this->get(route('admin.catalogos'))->assertOk()
        ->assertDontSeeLivewire(InstitucionesCatalogo::class)->assertDontSeeLivewire(CargosCatalogo::class)
        ->assertDontSee('Localidades de Ecuador');
    $this->get(route('admin.catalogos', ['q' => 'QA Universo']))->assertOk()
        ->assertSee('QA Universo Cargo')->assertSee('QA Universo Institución');
    $this->get($restringida)->assertForbidden();
    $this->get(route('admin.localidades'))->assertForbidden();

    Livewire::test(TablasCatalogos::class)->set('tabla', 'cargos')
        ->assertSeeHtml('data-tabla-catalogo="cargos"')->assertDontSee('QA Universo Institución')
        ->call('abrirRegistro', 'cargos', (string) $cargo)
        ->assertSet('editor.accion', 'editar')->assertSeeLivewire(CargosCatalogo::class)
        ->set('tabla', 'localidades-ecuador')->assertForbidden();
    Livewire::test(TablasCatalogos::class)->set('tabla', 'inexistente')->assertForbidden();

    $this->actingAs(User::factory()->depositante()->create());
    $this->get(route('admin.catalogos'))->assertForbidden();
    Livewire::test(TaxonIndex::class)->assertForbidden();
    Livewire::test(LocalidadIndex::class)->assertForbidden();
});

test('la cuadrícula universal carga más filas y conserva la búsqueda al ordenar', function (): void {
    $this->actingAs(User::factory()->curador()->create());
    $prefijo = 'QA Cargos grilla continua ';
    DB::table('usuarios.cargos_catalogo')->insert(array_map(
        static fn (int $numero): array => ['nombre' => $prefijo.sprintf('%02d', $numero), 'activo' => true],
        range(0, 16),
    ));

    $grilla = Livewire::test(TablasCatalogos::class)->set('tabla', 'cargos')->set('busqueda', $prefijo)
        ->assertSee($prefijo.'00')->assertDontSee($prefijo.'16');
    $ventana = $grilla->get('gridVentana');
    $grilla->call('cargarGrillaContinua', 1, $ventana['consulta'], $ventana['hasta'])
        ->assertSee($prefijo.'16')->assertSet('gridVentana.hasta', 2)
        ->call('aplicarOrdenGrilla', 'nombre', 'desc')
        ->assertSet('busqueda', $prefijo)->assertSee($prefijo.'16')->assertDontSee($prefijo.'00');
});

test('Matrices agrupa depósitos y plantillas y mantiene aparte Usuarios y perfiles', function (): void {
    $this->actingAs(User::factory()->administrador()->create(['must_change_password' => false]));

    $this->get(route('admin.matrices'))->assertOk()
        ->assertSee('Matriz de depósitos')->assertSee('Formato IPT (GBIF)')->assertSee('Plantilla de importación del catálogo')
        ->assertDontSeeHtml('value="usuarios"')->assertDontSeeHtml('value="instituciones"');
    $this->get(route('admin.catalogos'))->assertOk()
        ->assertDontSeeHtml('value="usuarios"')->assertDontSeeHtml('value="matriz-depositos"');
    $this->get(route('admin.formato-ipt'))->assertRedirect(route('admin.matrices', ['tabla' => 'formato-ipt']));
    $this->get(route('inventario.taxonomia.importar.plantilla.configurar'))
        ->assertRedirect(route('admin.matrices', ['tabla' => 'plantilla-catalogo']));
    $this->get(route('prestamos.curador.matriz.columnas'))->assertRedirect(route('admin.matrices', ['tabla' => 'matriz-depositos']));
    $this->get(route('admin.usuarios'))->assertOk()->assertSeeLivewire(App\Livewire\Administracion\GestionUsuarios::class);
});
