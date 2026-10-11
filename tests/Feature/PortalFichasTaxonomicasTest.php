<?php

declare(strict_types=1);

use App\Livewire\Administracion\FichasTaxonomicasPortal;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\CatalogoPublico\Infrastructure\FichasTaxonomicas;

uses(Tests\DatabaseFeatureTestCase::class);
uses(Tests\Concerns\ColeccionPortalAislada::class);

function fichaPortalFixture(): array
{
    $rama = []; $padre = null; $prefijo = 'Ficha'.Str::random(12);
    foreach (['phylum', 'clase', 'orden', 'familia', 'genero', 'especie'] as $rango) {
        $id = (string) Str::uuid();
        DB::table('taxonomia.taxones')->insert(['id' => $id, 'padre_id' => $padre, 'rango' => $rango, 'nombre_cientifico' => $prefijo.' '.$rango]);
        $rama[$rango] = $id; $padre = $id;
    }
    $especimen = (string) Str::uuid();
    DB::table('taxonomia.especimenes')->insert(['id' => $especimen, 'taxon_id' => $padre, 'codigo_catalogo' => $prefijo,
        'occurrence_id' => $prefijo, 'country' => 'Ecuador', 'locality_name' => 'Quito', 'state_province' => 'Pichincha',
        'decimal_latitude' => -.2, 'decimal_longitude' => -78.5]);
    DB::table('divulgacion.especimenes_divulgables')->insert(['id' => (string) Str::uuid(), 'especimen_id' => $especimen, 'publicado' => true]);
    return [$rama, $especimen];
}

test('curador y administrador editan fichas de todos los rangos por identidad con autoría', function (string $rol): void {
    [$rama] = fichaPortalFixture(); $usuario = User::factory()->{$rol}()->create();
    $panel = Livewire::actingAs($usuario)->test(FichasTaxonomicasPortal::class);
    foreach ($rama as $rango => $id) {
        $panel->call('seleccionar', $id)->set('descripcion', 'Descripción de '.$rango)->set('publicada', true)->call('guardar')->assertHasNoErrors();
        expect(DB::table(FichasTaxonomicas::TABLA)->where('taxon_id', $id)->first())->toMatchObject(['descripcion' => 'Descripción de '.$rango, 'updated_by' => $usuario->id]);
        $this->getJson(route('portal.ficha', ['taxon' => $id]))->assertOk()->assertJsonPath('descripcion', 'Descripción de '.$rango)->assertJsonPath('foto', null);
    }
})->with(['curador', 'administrador']);

test('la fotografía se comprime y su publicación respeta permisos vigentes del taxón', function (): void {
    [$rama, $especimen] = fichaPortalFixture();
    config(['deposit-storage.driver' => 'local', 'deposit-storage.require_remote' => false]); Storage::fake('local');
    $usuario = User::factory()->curador()->create();
    $panel = Livewire::actingAs($usuario)->test(FichasTaxonomicasPortal::class)->call('seleccionar', $rama['familia'])
        ->set('foto', UploadedFile::fake()->image('grupo.png', 720, 540))->set('fotoAlt', 'Organismo representativo de la familia')
        ->set('descripcion', 'Una familia de invertebrados.')->set('publicada', true)->call('guardar')->assertHasNoErrors();
    $ficha = app(FichasTaxonomicas::class)->buscar($rama['familia']);
    $binario = Storage::disk('local')->get($ficha->foto_ruta); $tamano = getimagesizefromstring($binario);
    expect($tamano['mime'])->toBe('image/webp')->and($tamano[0])->toBeLessThanOrEqual(360)->and($tamano[1])->toBeLessThanOrEqual(270)
        ->and(hash('sha256', $binario))->toBe($ficha->foto_sha256);
    $this->get(route('portal.ficha-foto', ['taxon' => $rama['familia']]))->assertOk()->assertHeader('Content-Type', 'image/webp');
    $panel->set('publicada', false)->call('guardar')->assertHasNoErrors();
    $this->getJson(route('portal.ficha', ['taxon' => $rama['familia']]))->assertOk()->assertJsonPath('descripcion', '')->assertJsonPath('foto', null);
    $this->get(route('portal.ficha-foto', ['taxon' => $rama['familia']]))->assertNotFound();
    $this->get(route('admin.ficha-foto', ['taxon' => $rama['familia']]))->assertOk();
    $panel->set('publicada', true)->call('guardar')->assertHasNoErrors();
    DB::table('divulgacion.especimenes_divulgables')->where('especimen_id', $especimen)->update(['family_visible' => false]);
    $this->getJson(route('portal.ficha', ['taxon' => $rama['familia']]))->assertNotFound();
    $this->get(route('portal.ficha-foto', ['taxon' => $rama['familia']]))->assertNotFound();
});

test('validación, conflicto editorial y despublicación conservan el contenido previo', function (): void {
    [$rama] = fichaPortalFixture(); $usuario = User::factory()->curador()->create();
    $panel = Livewire::actingAs($usuario)->test(FichasTaxonomicasPortal::class)->call('seleccionar', $rama['clase'])
        ->set('descripcion', '<script>contenido literal</script>')->set('publicada', true)->call('guardar')->assertHasNoErrors();
    $this->getJson(route('portal.ficha', ['taxon' => $rama['clase']]))->assertJsonPath('descripcion', '<script>contenido literal</script>');
    $panel->set('descripcion', str_repeat('a', 801))->call('guardar')->assertHasErrors('descripcion');
    expect(app(FichasTaxonomicas::class)->buscar($rama['clase'])->descripcion)->toBe('<script>contenido literal</script>');
    DB::table(FichasTaxonomicas::TABLA)->where('taxon_id', $rama['clase'])->update(['descripcion' => 'Edición de otra persona', 'updated_at' => now()->addSecond()]);
    $panel->set('descripcion', 'Edición atrasada')->call('guardar')->assertHasErrors('descripcion');
    expect(app(FichasTaxonomicas::class)->buscar($rama['clase'])->descripcion)->toBe('Edición de otra persona');
});

test('visitantes y depositantes no pueden administrar fichas ni ver fotos privadas', function (): void {
    [$rama] = fichaPortalFixture();
    $this->get(route('admin.fichas-taxonomicas'))->assertRedirect(route('login'));
    Livewire::test(FichasTaxonomicasPortal::class)->assertForbidden();
    $this->actingAs(User::factory()->depositante()->create())->get(route('admin.fichas-taxonomicas'))->assertForbidden();
    Livewire::test(FichasTaxonomicasPortal::class)->assertForbidden();
    $this->get(route('admin.ficha-foto', ['taxon' => $rama['clase']]))->assertForbidden();
});
