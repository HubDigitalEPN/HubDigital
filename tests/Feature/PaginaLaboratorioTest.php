<?php

use App\Enums\RolUsuario;
use App\Livewire\Administracion\ConfiguracionLaboratorio;
use App\Models\User;
use App\Services\PaginaLaboratorio;
use App\Support\PortalCopy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(\Tests\DatabaseFeatureTestCase::class);

beforeEach(function () {
    DB::table('usuarios.textos_portal')->where('clave', PaginaLaboratorio::CLAVE)->delete();
    PortalCopy::forget();
    config(['deposit-storage.driver' => 'local', 'deposit-storage.require_remote' => false, 'deposit-storage.local_disk' => 'local']);
    Storage::fake('local');
});
afterEach(fn () => PortalCopy::forget());

test('la página publica el texto solicitado y conserva la composición y navegación', function () {
    $this->get(route('laboratorio'))->assertOk()
        ->assertSee(config('pagina-laboratorio.textos.principal'))
        ->assertSeeInOrder(['>Inicio<', '>El Laboratorio<', 'Explorar la colección', 'Depósitos'], false)
        ->assertSee('data-lab-personal', false)->assertSee('Nuestro')->assertSee('Vladimir Carvajal')
        ->assertSee('personal-referencia.png')->assertSee('mailto:adrian.troya@epn.edu.ec', false);
    expect(app(PaginaLaboratorio::class)->leer()['fotos'])->toHaveCount(10);
});

test('solo administración y curador pueden editar la página', function () {
    $this->get(route('admin.laboratorio'))->assertRedirect(route('login'));
    foreach ([RolUsuario::DEPOSITANTE, RolUsuario::AYUDANTE_CURADOR] as $rol) {
        $usuario = User::factory()->create(['rol' => $rol]);
        Livewire::actingAs($usuario)->test(ConfiguracionLaboratorio::class)->assertForbidden();
    }
    $curador = User::factory()->curador()->create();
    Livewire::actingAs($curador)->test(ConfiguracionLaboratorio::class)->assertOk();
});

test('publicar texto y foto actualiza la página sin alterar otros textos y permite restaurar la foto', function () {
    $admin = User::factory()->administrador()->create();
    DB::table('usuarios.textos_portal')->updateOrInsert(['clave' => 'inicio.hero_descripcion'], ['contenido' => 'Texto de inicio independiente', 'created_at' => now(), 'updated_at' => now()]);
    PortalCopy::forget();
    $editor = Livewire::actingAs($admin)->test(ConfiguracionLaboratorio::class)
        ->set('textos.adrian_bio', '<script>alert(1)</script> Biología de hormigas')
        ->set('alternativos.adrian', 'Fotografía del curador')
        ->set('fotos.adrian', UploadedFile::fake()->image('curador.jpg', 600, 800))
        ->call('guardar')->assertHasNoErrors();
    $foto = app(PaginaLaboratorio::class)->leer()['fotos']['adrian'];
    expect($foto['ruta'])->toStartWith('divulgacion/laboratorio/');
    $this->get(route('laboratorio.foto', ['foto' => 'adrian', 'hash' => $foto['sha256']]))->assertOk()->assertHeader('Content-Type', 'image/webp');
    $this->get(route('laboratorio'))->assertOk()->assertSee('<script>alert(1)</script> Biología de hormigas')->assertDontSee('<script>alert(1)</script>', false);
    expect(DB::table('usuarios.textos_portal')->where('clave', 'inicio.hero_descripcion')->value('contenido'))->toBe('Texto de inicio independiente');
    $editor->set('restaurarFotos.adrian', true)->call('guardar')->assertHasNoErrors();
    expect(app(PaginaLaboratorio::class)->leer()['fotos']['adrian']['ruta'])->toBeNull();
    $this->get(route('laboratorio.foto', ['foto' => 'adrian', 'hash' => $foto['sha256']]))->assertNotFound();
});

test('un editor desactualizado y un archivo no fotográfico no sobrescriben contenido publicado', function () {
    $admin = User::factory()->administrador()->create();
    $primero = Livewire::actingAs($admin)->test(ConfiguracionLaboratorio::class);
    $segundo = Livewire::actingAs($admin)->test(ConfiguracionLaboratorio::class);
    $primero->set('textos.titulo', 'Laboratorio actualizado')->call('guardar')->assertHasNoErrors();
    $segundo->set('textos.titulo', 'Versión antigua')->call('guardar')->assertHasErrors('version');
    expect(app(PaginaLaboratorio::class)->leer()['textos']['titulo'])->toBe('Laboratorio actualizado');
    Livewire::actingAs($admin)->test(ConfiguracionLaboratorio::class)
        ->set('fotos.adrian', UploadedFile::fake()->createWithContent('foto.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'))
        ->call('guardar')->assertHasErrors('fotos.adrian');
    expect(app(PaginaLaboratorio::class)->leer()['fotos']['adrian']['ruta'])->toBeNull();
});
