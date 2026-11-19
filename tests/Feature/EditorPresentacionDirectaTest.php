<?php

use App\Livewire\Administracion\ConfiguracionTextosPortal;
use App\Livewire\Administracion\ConfiguracionLaboratorio;
use App\Models\User;
use App\Support\DisposicionContenido;
use App\Support\EditorPortal;
use App\Support\FotosEditoriales;
use App\Support\PortalCopy;
use App\Support\VistaEditorialInerte;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(Tests\DatabaseFeatureTestCase::class);
afterEach(fn () => PortalCopy::forget());

test('la maqueta retira scripts directivas y validaciones sin romper atributos del menu movil', function () {
    $html = '<html><body><nav id="movil" x-cloak @keydown.escape.window="if (abierto) { abierto = false; $nextTick(() => $refs.menuButton.focus()) }" class="menu"><a href="/">Inicio</a></nav><form action="/enviar" wire:submit="guardar"><input required pattern="[A-Z]+" oninput="alert(1)"><button type="submit">Enviar</button></form><script>alert(1)</script><script type="application/json" id="datos">{"a":"b"}</script></body></html>';
    $s = VistaEditorialInerte::sanear($html);
    expect($s)->toContain('id="movil"', 'class="menu"', 'hidden', 'novalidate', 'readonly', 'type="button"', 'id="datos"')
        ->not->toContain('$refs', '@keydown', 'wire:submit', 'action=', 'required', 'pattern=', 'oninput', 'alert(1)');
});

test('solo el curador recibe la maqueta inerte y los enlaces conservan las paginas editoriales', function () {
    $this->actingAs(User::factory()->curador()->create());
    foreach (['home', 'laboratorio', 'depositos.portal', 'portal.catalogo'] as $ruta) {
        $html = $this->get(route($ruta, ['_editor' => '1']))->assertOk()->getContent();
        expect($html)->toContain('data-vista-editorial="true"')->not->toMatch('/<script(?![^>]*type="application\/json")\b/i');
        expect($html)->not->toContain('wire:click=', 'wire:submit=', 'x-on:click=', '@click=', 'formaction=', '$refs.menuButton.focus()');
    }
    $this->get('/')->assertOk()->assertDontSee('data-vista-editorial="true"', false)->assertSee('hub-pwa-install-bootstrap');
});

test('el laboratorio se integra al portal sin pestaña duplicada y la cabecera mantiene sus elementos fijos', function () {
    $this->actingAs(User::factory()->administrador()->create());
    $this->get(route('admin.contenidos'))->assertOk()->assertDontSee('El Laboratorio: estructura y fotos')->assertSee('hub-editor-ayuda');
    $this->get(route('admin.contenidos', ['seccion' => 'laboratorio']))->assertOk()->assertSeeLivewire(ConfiguracionLaboratorio::class);
    $html = $this->get(route('home', ['_editor' => '1']))->getContent();
    expect(substr_count($html, 'data-editor-fixed'))->toBe(2);
    expect($html)->toContain('data-portal-copy="navegacion.institucion"', 'data-portal-copy="navegacion.panel_curador"', 'data-editor-no-text');
});

test('las fotos del portal se reemplazan por identidad con integridad y se pueden restaurar', function () {
    config(['deposit-storage.driver' => 'local', 'deposit-storage.require_remote' => false]); Storage::fake('local');
    $admin = User::factory()->administrador()->create();
    foreach (['inicio', 'depositos', 'logo'] as $id) {
        $ui = Livewire::actingAs($admin)->test(ConfiguracionTextosPortal::class)->call('seleccionarFotoEditorial', $id, 'Descripción original')
            ->assertSet('fotoEditorialAbierta', true)->set('fotoEditorial', UploadedFile::fake()->image('foto.jpg', 400, 300))
            ->set('fotoEditorialAlt', 'Fotografía editorial accesible')->call('guardarFotoEditorial')->assertHasNoErrors()->assertSet('fotoEditorialAbierta', false);
        $foto = FotosEditoriales::leer($id); $url = FotosEditoriales::url($id);
        expect(hash('sha256', Storage::disk('local')->get($foto['ruta'])))->toBe($foto['sha256']);
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/webp')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('portal.foto-editorial', ['foto' => $id, 'hash' => str_repeat('a', 64)]))->assertNotFound();
        $ui->call('seleccionarFotoEditorial', $id)->set('restaurarFotoEditorial', true)->call('guardarFotoEditorial')->assertHasNoErrors();
        expect(FotosEditoriales::url($id))->toBe(asset(FotosEditoriales::ORIGINALES[$id])); $this->get($url)->assertNotFound();
    }
    Livewire::actingAs($admin)->test(ConfiguracionTextosPortal::class)->call('seleccionarFotoEditorial', '../config')->assertStatus(422);
});

test('una foto invalida o una edicion concurrente conserva la foto publicada', function () {
    $admin = User::factory()->administrador()->create();
    $ui = Livewire::actingAs($admin)->test(ConfiguracionTextosPortal::class)->call('seleccionarFotoEditorial', 'inicio', 'Foto')
        ->set('fotoEditorial', UploadedFile::fake()->createWithContent('foto.svg', '<svg/>'))->call('guardarFotoEditorial')->assertHasErrors('fotoEditorial');
    $ui->call('seleccionarFotoEditorial', 'inicio', 'Foto');
    DB::table('usuarios.textos_portal')->updateOrInsert(['clave' => FotosEditoriales::clave('inicio')], ['contenido' => '{"alt":"Edición ajena"}', 'updated_at' => now()]); PortalCopy::forget();
    $ui->set('fotoEditorialAlt', 'Mi edición')->call('guardarFotoEditorial')->assertHasErrors('fotoEditorial');
    expect(FotosEditoriales::alt('inicio', ''))->toBe('Edición ajena');
});
