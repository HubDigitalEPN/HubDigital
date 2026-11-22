<?php

declare(strict_types=1);

use App\Livewire\Administracion\CargosCatalogo;
use App\Livewire\Administracion\FuentesRevocacionFirma;
use App\Livewire\Administracion\GruposAnimalesCatalogo;
use App\Livewire\Administracion\InstitucionesCatalogo;
use App\Livewire\Administracion\LocalidadesEcuadorCatalogo;
use App\Livewire\Administracion\TablasCatalogos;
use App\Livewire\Administracion\TablasCatalogosDatos;
use App\Models\SystemMailConfiguration;
use App\Models\User;
use App\Support\ConfiguracionIpt;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\LocalidadIndex;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\TaxonIndex;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\ConfiguracionPlantillaCatalogoIndex;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\ConfiguracionPlantillaCatalogo;

uses(Tests\DatabaseFeatureTestCase::class);

beforeEach(fn () => $this->withoutExceptionHandling([
    Symfony\Component\HttpKernel\Exception\HttpException::class,
    Illuminate\Auth\AuthenticationException::class,
]));

test('cada enlace anterior abre la tabla física con sus columnas de datos', function (string $anterior, string $tabla, string $fisica, string $componente): void {
    $this->actingAs(User::factory()->administrador()->create());
    $nueva = route('admin.catalogos', ['tabla' => $tabla]);

    $this->get(route($anterior, ['tabla' => 'otra', 'q' => 'QA']))
        ->assertRedirect(route('admin.catalogos', ['tabla' => $tabla, 'q' => 'QA']));
    $respuesta = $this->get($nueva)->assertOk()->assertSeeLivewire(TablasCatalogosDatos::class)
        ->assertDontSeeLivewire($componente)->assertSee($fisica)
        ->assertSeeHtml('data-tabla-catalogo="'.$tabla.'"')->assertSeeHtml('data-registros-catalogos');
    $dom = new DOMDocument;
    $dom->loadHTML($respuesta->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
    $xpath = new DOMXPath($dom);
    $contexto = $xpath->query('//*[@data-tablas-catalogos]//p[code]')->item(0);
    $nuevo = $xpath->query('//*[@data-registros-catalogos]//*[contains(@class, "hub-ig-action-pair")]/button[1]/following-sibling::*[1]')->item(0);
    $cabeceras = $xpath->query('//*[@data-registros-catalogos]//thead/tr/th');
    $columnas = array_map(fn (DOMElement $cabecera) => $cabecera->getAttribute('data-grid-field'),
        array_slice(iterator_to_array($cabeceras), 2));
    $auditoria = ['created_at', 'updated_at', 'deleted_at', 'updated_by', 'actualizado_por', 'actualizado_por_id'];
    $clavePrimaria = collect(Schema::getIndexes($fisica))->firstWhere('primary', true)['columns'];
    $columnasDeDatos = array_values(array_diff(array_column(Schema::getColumns($fisica), 'name'), $auditoria, $clavePrimaria));

    expect($contexto?->textContent)->toStartWith($fisica.' (')->toContain(' - ')->toEndWith(')')
        ->and($contexto?->getElementsByTagName('strong')->length)->toBe(1)
        ->and(trim($nuevo?->textContent ?? ''))->toBe('Nuevo registro')
        ->and($cabeceras->item(0)->hasAttribute('data-grid-action'))->toBeTrue()
        ->and(trim($cabeceras->item(1)->textContent))->toBe('N.º')
        ->and($columnas)->toBe($columnasDeDatos);
})->with([
    ['admin.instituciones', 'instituciones', 'usuarios.instituciones_catalogo', InstitucionesCatalogo::class],
    ['admin.cargos', 'cargos', 'usuarios.cargos_catalogo', CargosCatalogo::class],
    ['admin.grupos-animales', 'grupos-animales', 'recepciones.catalogo_grupos_invertebrados', GruposAnimalesCatalogo::class],
    ['admin.fuentes-revocacion', 'fuentes-revocacion', 'recepciones.fuentes_revocacion_firma', FuentesRevocacionFirma::class],
    ['admin.localidades', 'localidades-ecuador', 'recepciones.localidades_ecuador_catalogo', LocalidadesEcuadorCatalogo::class],
    ['inventario.taxonomia.localidades', 'localidades-coleccion', 'taxonomia.localidades', LocalidadIndex::class],
    ['inventario.taxonomia.taxones', 'taxones', 'taxonomia.taxones', TaxonIndex::class],
]);

test('la cuadrícula comparte la búsqueda y conserva los permisos de curaduría y administración', function (): void {
    $restringida = route('admin.catalogos', ['tabla' => 'localidades-ecuador']);
    $this->get(route('admin.catalogos'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->receptor()->create());
    $cargo = DB::table('usuarios.cargos_catalogo')->insertGetId(['nombre' => 'QA Universo Cargo', 'activo' => true]);
    DB::table('usuarios.instituciones_catalogo')->insert(['nombre' => 'QA Universo Institución', 'activo' => true]);
    $this->get(route('admin.catalogos'))->assertOk()
        ->assertDontSeeLivewire(InstitucionesCatalogo::class)->assertDontSeeLivewire(CargosCatalogo::class)
        ->assertDontSee('Localidades de Ecuador');
    $this->get(route('admin.catalogos', ['q' => 'QA Universo']))->assertOk()
        ->assertDontSee('QA Universo Cargo')->assertSee('QA Universo Institución')
        ->assertDontSeeHtml('data-grid-field="id"')->assertSeeHtml('data-grid-field="codigo_ces"')
        ->assertDontSeeHtml('data-grid-field="catalogo"')->assertDontSeeHtml('data-grid-field="detalle"');
    $this->get(route('admin.catalogos', ['tabla' => 'cargos', 'q' => 'QA Universo']))
        ->assertOk()->assertSee('QA Universo Cargo')->assertDontSee('QA Universo Institución');
    $this->get($restringida)->assertForbidden();
    $this->get(route('admin.localidades'))->assertForbidden();

    Livewire::test(TablasCatalogosDatos::class)->call('seleccionarTabla', 'cargos')
        ->assertSeeHtml('data-tabla-catalogo="cargos"')->assertDontSee('QA Universo Institución')
        ->call('editar', (string) $cargo)
        ->assertSet('editando', (string) $cargo)->assertSet('datos.nombre', 'QA Universo Cargo')
        ->call('seleccionarTabla', 'localidades-ecuador')->assertForbidden();
    Livewire::test(TablasCatalogosDatos::class)->set('tabla', 'inexistente')->assertForbidden();
    Livewire::test(TablasCatalogosDatos::class)->call('seleccionarTabla', 'sistema')->assertForbidden();

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

    $grilla = Livewire::test(TablasCatalogosDatos::class)->call('seleccionarTabla', 'cargos')->set('busqueda', $prefijo)
        ->call('aplicarOrdenGrilla', 'nombre', 'asc')
        ->assertSee($prefijo.'00')->assertDontSee($prefijo.'16');
    $ventana = $grilla->get('gridVentana');
    $grilla->call('cargarGrillaContinua', 1, $ventana['consulta'], $ventana['hasta'])
        ->assertSee($prefijo.'16')->assertSeeHtml('data-ordinal="17"')->assertSet('gridVentana.hasta', 2)
        ->call('aplicarOrdenGrilla', 'nombre', 'desc')
        ->assertSet('busqueda', $prefijo)->assertSee($prefijo.'16')->assertDontSee($prefijo.'00');
});

test('la cuadrícula conserva un cargo utilizado en un perfil aunque la referencia sea de texto', function (): void {
    $this->actingAs(User::factory()->curador()->create());
    $nombre = 'QA Cargo en uso';
    $id = DB::table('usuarios.cargos_catalogo')->insertGetId(['nombre' => $nombre, 'activo' => true]);
    User::factory()->depositante()->create(['cargo' => $nombre]);

    Livewire::test(TablasCatalogosDatos::class)->call('seleccionarTabla', 'cargos')
        ->call('solicitarEliminar', (string) $id)->call('eliminar')->assertHasErrors('registro');
    $this->assertDatabaseHas('usuarios.cargos_catalogo', ['id' => $id, 'nombre' => $nombre]);
});

test('la cuadrícula crea edita y elimina un registro de la tabla seleccionada', function (): void {
    $this->actingAs(User::factory()->curador()->create());
    $grilla = Livewire::test(TablasCatalogosDatos::class)->call('seleccionarTabla', 'cargos')
        ->call('nuevo')->assertSet('datos.activo', '1')->assertSeeHtml('wire:model="datos.activo"')
        ->set('datos.nombre', 'QA Cargo Operaciones')->set('datos.activo', 'tal vez')
        ->call('guardar')->assertHasErrors(['activo' => 'boolean'])
        ->set('datos.activo', '1')
        ->call('guardar')->assertHasNoErrors();
    $cargo = DB::table('usuarios.cargos_catalogo')->where('nombre', 'QA Cargo Operaciones')->sole();
    $grilla->call('nuevo')->set('datos.nombre', 'QA Cargo Paralelo')->set('datos.activo', '1')
        ->call('guardar')->assertHasNoErrors();
    $segundo = DB::table('usuarios.cargos_catalogo')->where('nombre', 'QA Cargo Paralelo')->sole();

    $grilla->set('busqueda', 'QA Cargo Operaciones')->assertSeeHtml('data-catalogo-menu-fila')
        ->call('editar', (string) $cargo->id)->assertDontSeeHtml('wire:model="datos.id"')
        ->assertSet('datos.activo', '1')->assertSeeHtml('wire:model="datos.activo"')
        ->set('datos.id', (string) $segundo->id)->set('datos.nombre', 'QA Cargo Operaciones Editado')
        ->set('datos.activo', '0')->call('guardar')->assertHasNoErrors();
    $this->assertDatabaseHas('usuarios.cargos_catalogo', [
        'id' => $cargo->id,
        'nombre' => 'QA Cargo Operaciones Editado',
        'activo' => false,
    ]);

    $grilla->call('solicitarEliminar', (string) $cargo->id)
        ->assertSet('borrando', true)->call('eliminar')->assertHasNoErrors();
    $this->assertDatabaseMissing('usuarios.cargos_catalogo', ['id' => $cargo->id]);
    $this->assertDatabaseHas('usuarios.cargos_catalogo', ['id' => $segundo->id, 'nombre' => 'QA Cargo Paralelo']);
});

test('la cuadrícula protege una localidad con dependencias y permite eliminarla tras su hija', function (): void {
    $this->actingAs(User::factory()->curador()->create());
    $padre = (string) Str::uuid();
    $hija = (string) Str::uuid();
    $prefijo = 'QA Integridad localidad '.Str::uuid();
    DB::table('taxonomia.localidades')->insert([
        ['id' => $padre, 'nombre_canonico' => $prefijo.' Provincia', 'rango' => 'provincia', 'padre_id' => null],
        ['id' => $hija, 'nombre_canonico' => $prefijo.' Sitio', 'rango' => 'sitio', 'padre_id' => $padre],
    ]);

    $grilla = Livewire::test(TablasCatalogosDatos::class)->call('seleccionarTabla', 'localidades-coleccion')
        ->set('busqueda', $prefijo)->call('editar', $hija)
        ->assertSeeHtml('wire:model="datos.rango"')->assertSeeHtml('id="lov-datos.padre_id-boton"')
        ->call('buscarOpcionesRelacion', 'datos.padre_id', $prefijo.' Provincia')
        ->assertReturned(['opciones' => [['valor' => $padre, 'etiqueta' => $prefijo.' Provincia (provincia)']], 'hayMas' => false])
        ->set('datos.rango', 'prohibido')->call('guardar')->assertHasErrors(['rango' => 'in'])
        ->set('datos.rango', 'sitio')->call('guardar')->assertHasNoErrors()
        ->call('solicitarEliminar', $padre)
        ->call('eliminar')->assertHasErrors('registro');
    $this->assertDatabaseHas('taxonomia.localidades', ['id' => $padre]);
    $this->assertDatabaseHas('taxonomia.localidades', ['id' => $hija, 'padre_id' => $padre]);

    $grilla->call('solicitarEliminar', $hija)->call('eliminar')->assertHasNoErrors();
    $this->assertDatabaseMissing('taxonomia.localidades', ['id' => $hija]);
    $grilla->call('solicitarEliminar', $padre)->call('eliminar')->assertHasNoErrors();
    $this->assertDatabaseMissing('taxonomia.localidades', ['id' => $padre]);
});

test('correo muestra protegida la contraseña y rechaza un segundo registro', function (): void {
    $administrador = User::factory()->administrador()->create(['must_change_password' => false]);
    $this->actingAs($administrador);
    $secreto = 'token-smtptest-ficticio';
    $otroEncriptador = new Encrypter(random_bytes(32), 'AES-256-CBC');
    DB::table('usuarios.configuracion_correo')->updateOrInsert(['id' => 1], [
        'host' => 'smtp.example.test',
        'port' => 587,
        'username' => 'correo@example.test',
        'password' => $otroEncriptador->encryptString($secreto),
        'from_address' => 'correo@example.test',
        'from_name' => 'Correo QA',
        'updated_by' => $administrador->id,
    ]);
    $cifrado = DB::table('usuarios.configuracion_correo')->where('id', 1)->value('password');
    $grilla = Livewire::test(TablasCatalogosDatos::class)->call('seleccionarTabla', 'sistema')
        ->assertSee('usuarios.configuracion_correo')->assertSee('Admite un solo registro')->assertSee('Protegida')
        ->assertDontSeeHtml('wire:click="nuevo"')->assertDontSee($secreto)->assertDontSee($cifrado);

    $grilla->set('datos.from_name', 'Segundo correo QA')->call('guardar', $secreto)
        ->assertHasErrors('registro')->assertSee('Esta tabla admite un solo registro.');
    expect(DB::table('usuarios.configuracion_correo')->count())->toBe(1)
        ->and(SystemMailConfiguration::query()->findOrFail(1)->from_name)->toBe('Correo QA');

    $grilla->call('editar', '1')->assertSet('datos.password', null)
        ->set('datos.from_name', 'Correo QA Editado')->call('guardar')->assertHasErrors('registro')
        ->call('guardar', $secreto)->assertHasNoErrors();
    $grilla->call('editar', '1')->set('datos.from_name', 'Correo QA Conservado')->call('guardar')->assertHasNoErrors();
    expect(SystemMailConfiguration::query()->findOrFail(1)->password)->toBe($secreto);
    $grilla->call('solicitarEliminar', '1')->call('eliminar')->assertHasNoErrors();
    $this->assertDatabaseMissing('usuarios.configuracion_correo', ['id' => 1]);
    $this->assertDatabaseHas('usuarios.estado_configuracion_correo', ['id' => 1]);
});

test('la configuración de expediente también impide crear un segundo registro', function (): void {
    $this->actingAs(User::factory()->administrador()->create(['must_change_password' => false]));
    DB::table('usuarios.configuracion_expediente')->updateOrInsert(
        ['id' => 1],
        ['prefijo' => 'QA-INV-DEP'],
    );

    Livewire::test(TablasCatalogosDatos::class)->call('seleccionarTabla', 'siglas-expedientes')
        ->assertSee('Admite un solo registro')->assertDontSeeHtml('wire:click="nuevo"')
        ->set('datos.id', '2')->set('datos.prefijo', 'QA-OTRO-DEP')
        ->call('guardar')->assertHasErrors('registro');
    $this->assertDatabaseMissing('usuarios.configuracion_expediente', ['prefijo' => 'QA-OTRO-DEP']);
    $this->assertDatabaseHas('usuarios.configuracion_expediente', [
        'id' => 1,
        'prefijo' => 'QA-INV-DEP',
    ]);
});

test('Matrices agrupa depósitos y plantillas y mantiene aparte Usuarios y perfiles', function (): void {
    $this->actingAs(User::factory()->administrador()->create(['must_change_password' => false]));

    $this->get(route('admin.matrices'))->assertOk()
        ->assertSee('Matriz de depósitos')->assertSee('Formato IPT (GBIF)')->assertSee('Plantilla de importación del catálogo')
        ->assertSee('Campo Darwin Core')->assertSee('Cabecera Excel')->assertSee('Requisito')
        ->assertDontSeeHtml('data-grid-field="catalogo"')
        ->assertDontSeeHtml('value="usuarios"')->assertDontSeeHtml('value="instituciones"');
    $this->get(route('admin.matrices', ['tabla' => 'formato-ipt']))->assertOk()
        ->assertSeeLivewire(TablasCatalogos::class)->assertDontSeeLivewire(App\Livewire\Administracion\ConfiguracionFormatoIpt::class)
        ->assertSee('Campo de origen')->assertSee('Cabecera IPT')->assertSeeHtml('data-registros-catalogos')
        ->assertSeeHtml('data-catalogo-fila="formato-ipt"')->assertSee('Nueva columna');
    $this->get(route('admin.matrices', ['tabla' => 'plantilla-catalogo']))->assertOk()
        ->assertSeeLivewire(TablasCatalogos::class)->assertDontSeeLivewire(ConfiguracionPlantillaCatalogoIndex::class)
        ->assertSee('Descripción y ejemplo')->assertSee('Subir plantilla modificada')->assertSeeHtml('data-registros-catalogos')
        ->assertSeeHtml('data-catalogo-fila="plantilla-catalogo"');
    $this->get(route('admin.catalogos'))->assertOk()
        ->assertDontSeeHtml('value="usuarios"')->assertDontSeeHtml('value="matriz-depositos"');
    $this->get(route('admin.formato-ipt'))->assertRedirect(route('admin.matrices', ['tabla' => 'formato-ipt']));
    $this->get(route('inventario.taxonomia.importar.plantilla.configurar'))
        ->assertRedirect(route('admin.matrices', ['tabla' => 'plantilla-catalogo']));
    $this->get(route('prestamos.curador.matriz.columnas'))->assertRedirect(route('admin.matrices', ['tabla' => 'matriz-depositos']));
    $this->get(route('admin.usuarios'))->assertOk()->assertSeeLivewire(App\Livewire\Administracion\GestionUsuarios::class);
    $this->get(route('admin.catalogos', ['tabla' => 'botones']))->assertForbidden();
    $this->get(route('admin.botones'))->assertOk()->assertSeeLivewire(App\Livewire\Administracion\GestionBotones::class);
});

test('editar una columna de importación conserva su identificador y reconoce la cabecera anterior', function (): void {
    $this->actingAs(User::factory()->administrador()->create(['must_change_password' => false]));
    $plantilla = app(ConfiguracionPlantillaCatalogo::class);
    $antes = collect($plantilla->leer()['columnas'])->firstWhere('campo', 'occurrenceID');

    Livewire::test(TablasCatalogos::class)->call('seleccionarTabla', 'plantilla-catalogo')->call('editarColumna', 'occurrenceID')
        ->assertSet('datos.cabecera', $antes['cabecera'])
        ->set('datos.requisito', 'opcional')->call('guardarColumna')->assertHasErrors('matriz')
        ->set('datos.requisito', 'obligatorio')->set('datos.cabecera', 'Identificador QA de catálogo')
        ->call('guardarColumna')->assertHasNoErrors();

    $despues = collect($plantilla->leer()['columnas'])->firstWhere('campo', 'occurrenceID');
    expect($despues['cabecera'])->toBe('Identificador QA de catálogo')
        ->and($despues['alias'])->toContain($antes['cabecera']);
});

test('Matrices administra columnas de IPT desde la cuadrícula y protege las obligatorias', function (): void {
    $this->actingAs(User::factory()->administrador()->create(['must_change_password' => false]));
    $ipt = app(ConfiguracionIpt::class);
    $definicion = $ipt->leer();
    $campo = array_values(array_diff(ConfiguracionIpt::terminos(), array_column($definicion['columnas'], 'campo')))[0];
    $grilla = Livewire::test(TablasCatalogos::class)->call('seleccionarTabla', 'formato-ipt')
        ->call('nuevo')->set('datos.campo', $campo)->set('datos.origen', $campo)->set('datos.orden', 1)
        ->call('guardarColumna')->assertHasNoErrors()->assertSee('Transacción Efectuada');
    expect($ipt->leer()['columnas'][0]['campo'])->toBe($campo);

    $grilla->call('editarColumna', $campo)->set('datos.ancho', 24)->set('datos.orden', 2)
        ->call('guardarColumna')->assertHasNoErrors();
    expect($ipt->leer()['columnas'][1]['ancho'])->toBe(24);

    $grilla->call('solicitarEliminar', 'eventDate')->call('confirmarCambio')->assertHasErrors('matriz')
        ->call('solicitarEliminar', $campo)->call('confirmarCambio')->assertHasNoErrors();
    expect($ipt->leer()['columnas'])->toEqual($definicion['columnas']);
});
