<?php

declare(strict_types=1);

use App\Livewire\Administracion\ConfiguracionTextosSistema;
use App\Models\User;
use App\Support\TextosSistema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\ColumnasCatalogo;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\ConfiguracionPlantillaCatalogo;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\EspecimenIndex;

uses(Tests\DatabaseFeatureTestCase::class);

afterEach(fn () => TextosSistema::olvidar());

test('administración edita textos del sistema con autoría y los publica como texto seguro', function (): void {
    $admin = User::factory()->administrador()->create();
    $original = 'Abrir una ficha de prueba';
    $contenido = '</script><script>alert(1)</script>';
    Livewire::actingAs($admin)->test(ConfiguracionTextosSistema::class)
        ->set('textoAdicional', $original)->call('agregar')->set('contenido', $contenido)->call('guardar')->assertHasNoErrors();
    expect(TextosSistema::diccionario()[$original])->toBe($contenido);
    expect(DB::table('usuarios.textos_sistema')->where('original', $original)->value('actualizado_por'))->toBe($admin->id);
    $this->get(route('home'))->assertDontSee($contenido, false)->assertSee('textos-sistema-datos', false);
    Livewire::test(ConfiguracionTextosSistema::class)->call('editar', hash('sha256', $original))->call('restaurar')->assertHasNoErrors();
    expect(TextosSistema::diccionario())->not->toHaveKey($original);
});

test('visitantes y curadores no pueden administrar los textos internos del sistema', function (): void {
    Livewire::test(ConfiguracionTextosSistema::class)->assertForbidden();
    Livewire::actingAs(User::factory()->curador()->create())->test(ConfiguracionTextosSistema::class)->assertForbidden();
});

test('una columna nueva se puede retirar sin uso y queda protegida después de guardar datos incluso al vaciarla', function (): void {
    $admin = User::factory()->administrador()->create(); $this->actingAs($admin);
    $plantilla = app(ConfiguracionPlantillaCatalogo::class); $def = $plantilla->leer();
    $extra = ['campo' => 'extra_'.str_replace('-', '', (string) Str::uuid()), 'cabecera' => 'Observación del curador',
        'descripcion' => 'Descripción de prueba', 'requisito' => 'opcional', 'formato' => 'texto', 'alias' => [], 'personalizada' => true];
    $nueva = $def; $nueva['columnas'][] = $extra;
    $plantilla->guardar($nueva, $plantilla::huella($def), $admin->id);
    $plantilla->guardar($def, $plantilla::huella($nueva), $admin->id);
    $plantilla->guardar($nueva, $plantilla::huella($def), $admin->id);
    $id = (string) Str::uuid();
    DB::table('taxonomia.especimenes')->insert(['id' => $id, 'codigo_catalogo' => 'QA-COLUMNA-'.$id, 'estado' => 'disponible']);
    $columnas = app(ColumnasCatalogo::class);
    $columnas->guardarValor($id, $extra['campo'], 'Valor original');
    expect($columnas->completar([['id' => $id]])[0][$extra['campo']])->toBe('Valor original');
    $columnas->guardarValor($id, $extra['campo'], null);
    expect(fn () => $plantilla->guardar($def, $plantilla::huella($nueva), $admin->id))->toThrow(DomainException::class, 'ya fue utilizada');
    expect(DB::table('taxonomia.historial_columnas_especimen')->where('campo', $extra['campo'])->count())->toBe(2);
});

test('editar ubicación de una ficha histórica sin fecha ni colector conserva los datos ausentes', function (): void {
    $curador = User::factory()->curador()->create(); $id = (string) Str::uuid();
    DB::table('taxonomia.especimenes')->insert(['id' => $id, 'codigo_catalogo' => 'QA-EDITAR-'.$id, 'estado' => 'disponible', 'localidad' => 'Sitio anterior']);
    Livewire::actingAs($curador)->test(EspecimenIndex::class)->call('abrirEditModal', $id)
        ->set('editLocalidad', 'Sitio corregido')->call('actualizarEspecimen')->assertHasNoErrors()->assertSet('showEditModal', false);
    $registro = DB::table('taxonomia.especimenes')->where('id', $id)->first();
    expect($registro->localidad)->toBe('Sitio corregido')->and($registro->fecha_colecta)->toBeNull()->and($registro->colector)->toBeNull();
});
