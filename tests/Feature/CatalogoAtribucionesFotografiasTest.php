<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\CatalogoPublico\Application\UseCases\ConsultarGaleriaTaxon\ConsultarGaleriaTaxonHandler;
use Modules\CatalogoPublico\Application\UseCases\ConsultarGaleriaTaxon\ConsultarGaleriaTaxonInput;
use Modules\CatalogoPublico\Presentation\Http\Controllers\GestionImagenesTaxonomicas;
use Modules\CatalogoPublico\Presentation\Http\Controllers\PortalCatalogo;

uses(Tests\DatabaseFeatureTestCase::class);
uses(Tests\Concerns\ColeccionPortalAislada::class);

function atribucionesFotografiaFixture(): array
{
    $f = cartografiaRealFixture();
    $padre = $f['filo'];
    foreach (['clase', 'orden', 'familia', 'genero'] as $rango) {
        $id = (string) Str::uuid();
        DB::table('taxonomia.taxones')->insert(['id' => $id, 'padre_id' => $padre,
            'nombre_cientifico' => $rango === 'genero' ? $f['prefijo'] : $f['prefijo'].$rango,
            'rango' => $rango, 'autor' => 'QA', 'anio_descripcion' => 2026]);
        $padre = $id;
    }
    DB::table('taxonomia.taxones')->whereIn('id', $f['taxones'])->update(['padre_id' => $padre]);
    return $f;
}

test('el editor guarda atribuciones completas y las publica sin reescribir el original ni perder datos históricos', function (): void {
    $this->actingAs(User::factory()->curador()->create());
    $f = atribucionesFotografiaFixture();
    config(['deposit-storage.driver' => 'local', 'deposit-storage.require_remote' => false]);
    Storage::fake('local');
    $archivo = UploadedFile::fake()->image('original.png', 40, 30);
    $bytes = file_get_contents($archivo->getRealPath());
    $editor = Livewire::test(GestionImagenesTaxonomicas::class)
        ->call('seleccionarEspecie', $f['prefijo'].' alfa', $f['prefijo'])
        ->set('occurrenceSeleccionado', $f['codigos'][0])
        ->set('photographer', "Ana O'Neill")->set('license', 'CC BY-NC 4.0')
        ->set('licenseHolder', 'Titular indicado')->set('licenseInstitution', 'Institución indicada')
        ->set('contact', 'fotografia@example.org')->set('vista', 'D')->set('imagen', $archivo)
        ->call('subir')->assertHasNoErrors()->assertSee('sin añadir texto');
    $foto = DB::table('divulgacion.imagenes_taxonomicas')->where('occurrence_id', $f['codigos'][0])->first();
    expect($foto)->not->toBeNull()
        ->and($foto->photographer)->toBe("Ana O'Neill")
        ->and($foto->license)->toBe('CC BY-NC 4.0')->and($foto->license_holder)->toBe('Titular indicado')
        ->and($foto->license_institution)->toBe('Institución indicada')->and($foto->contact)->toBe('fotografia@example.org')
        ->and($foto->view)->toBe('Dorsal view')->and((bool) $foto->marca_agua_aplicada)->toBeFalse()
        ->and(Storage::disk('local')->get($foto->ruta))->toBe($bytes);
    $salida = app(ConsultarGaleriaTaxonHandler::class)->handle(new ConsultarGaleriaTaxonInput('species', $f['prefijo'].' alfa'));
    expect($salida->imagenes[0]['atribuciones'])->toMatchArray(['photographer' => "Ana O'Neill", 'license' => 'CC BY-NC 4.0', 'view' => 'Dorsal view']);

    $editor->call('editarAtribuciones', $foto->id)->set('photographer', 'Fotógrafo corregido')
        ->set('licenseHolder', '')->set('contact', '<script>alert(1)</script>')
        ->call('guardarAtribuciones')->assertHasNoErrors()->assertSee('Atribuciones actualizadas');
    $actual = DB::table('divulgacion.imagenes_taxonomicas')->find($foto->id);
    expect($actual->photographer)->toBe('Fotógrafo corregido')
        ->and($actual->autor_nombre)->toBe($foto->autor_nombre)->and($actual->autor_apellido)->toBe($foto->autor_apellido)
        ->and($actual->autor_nombre_completo)->toBe($foto->autor_nombre_completo)
        ->and($actual->ruta)->toBe($foto->ruta)->and($actual->sha256)->toBe($foto->sha256);
    $html = view('catalogopublico::components.atribuciones-imagen', ['datos' => ['contact' => $actual->contact]])->render();
    expect($html)->toContain('&lt;script&gt;', 'No informado')->not->toContain('<script>', 'href="javascript:');
});

test('los metadatos opcionales no inventan una licencia y rechazan entradas excesivas antes de almacenar', function (): void {
    $this->actingAs(User::factory()->curador()->create());
    $f = atribucionesFotografiaFixture();
    config(['deposit-storage.driver' => 'local', 'deposit-storage.require_remote' => false]);
    Storage::fake('local');
    $editor = Livewire::test(GestionImagenesTaxonomicas::class)
        ->call('seleccionarEspecie', $f['prefijo'].' alfa', $f['prefijo'])
        ->set('occurrenceSeleccionado', $f['codigos'][0])
        ->set('photographer', str_repeat('A', 256))->set('imagen', UploadedFile::fake()->image('opcional.png'))
        ->call('subir')->assertHasErrors(['photographer' => 'max']);
    expect(DB::table('divulgacion.imagenes_taxonomicas')->where('occurrence_id', $f['codigos'][0])->count())->toBe(0);
    $editor->set('photographer', '')->call('subir')->assertHasNoErrors();
    $foto = DB::table('divulgacion.imagenes_taxonomicas')->where('occurrence_id', $f['codigos'][0])->first();
    expect($foto->photographer)->toBe('')->and($foto->license)->toBe('')->and($foto->contact)->toBe('');
});

test('el editor no expone la gestión a visitantes ni permite editar una imagen ajena al catálogo divulgado', function (): void {
    $this->get(route('divulgacion.imagenes'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->curador()->create());
    $f = atribucionesFotografiaFixture();
    $id = (string) Str::uuid();
    DB::table('divulgacion.imagenes_taxonomicas')->insert(['id' => $id, 'occurrence_id' => 'SIN-ESPECIMEN',
        'ruta' => 'divulgacion/imagenes/ajena.png', 'nombre_original' => 'ajena.png', 'disco' => 'r2',
        'autor_nombre' => 'Autor', 'autor_apellido' => 'Anterior', 'autor_nombre_completo' => 'Autor Anterior']);
    Livewire::test(GestionImagenesTaxonomicas::class)->call('editarAtribuciones', $id)->assertStatus(404);
});

test('las páginas de 500 y 1000 conservan selección, orden y paginación sin modificar el límite de localidad', function (): void {
    $this->actingAs(User::factory()->curador()->create());
    $f = cartografiaRealFixture();
    $base = (array) DB::table('taxonomia.especimenes')->where('id', $f['ids'][0])->first();
    unset($base['coordenadas_otras_regiones'], $base['busqueda_global']);
    $filas = $publicados = [];
    for ($i = 0; $i < 1001; $i++) {
        $id = (string) Str::uuid();
        $codigo = 'QA-PAGINA-GRANDE-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT);
        $filas[] = array_replace($base, ['id' => $id, 'occurrence_id' => $codigo, 'codigo_catalogo' => $codigo]);
        $publicados[] = ['id' => (string) Str::uuid(), 'especimen_id' => $id, 'publicado' => true];
    }
    foreach (array_chunk($filas, 100) as $grupo) DB::table('taxonomia.especimenes')->insert($grupo);
    foreach (array_chunk($publicados, 100) as $grupo) DB::table('divulgacion.especimenes_divulgables')->insert($grupo);
    $portal = Livewire::withQueryParams(['vista' => 'registros', 'fph' => $f['filo'], 'nivel' => 'species', 'taxon' => $f['prefijo'].' alfa'])->test(PortalCatalogo::class)
        ->call('ajustarRegistrosPorPagina', 500)->assertSet('pagina', 1)
        ->assertViewHas('registrosVista', fn ($r) => count($r) === 500)
        ->call('cambiarPagina', 2);
    $segunda = array_column($portal->viewData('registrosVista'), 'especimen_id');
    $portal->call('cambiarPagina', 1);
    expect(array_intersect($segunda, array_column($portal->viewData('registrosVista'), 'especimen_id')))->toBe([]);
    $portal->call('ajustarRegistrosPorPagina', 1000)->assertSet('pagina', 1)
        ->assertViewHas('registrosVista', fn ($r) => count($r) === 1000)
        ->call('cambiarPagina', 2)->assertViewHas('registrosVista', fn ($r) => count($r) === 3)
        ->call('ordenarRegistros', 'occurrence_id', 'desc')->assertSet('pagina', 1)
        ->call('ajustarRegistrosPorPagina', 100000)->assertSet('registrosPorPagina', 1000)
        ->call('ajustarRegistrosPorPagina', 1000, true)->assertSet('registrosPorPaginaCelda', 100);
});
