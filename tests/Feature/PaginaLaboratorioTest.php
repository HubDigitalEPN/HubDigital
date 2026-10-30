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

test('los bloques adicionales publican texto largo y fotos en el orden elegido y permiten retirarlos', function () {
    $admin = User::factory()->administrador()->create();
    $editor = Livewire::actingAs($admin)->test(ConfiguracionLaboratorio::class)->call('agregarBloque');
    $primero = $editor->get('bloques')[0]['id'];
    $texto = str_repeat('Investigación y conservación de hormigas. ', 180).' FINAL-DEL-TEXTO <script>no ejecutar</script>';
    $editor->set('bloques.0.titulo', 'Galería del laboratorio')->set('bloques.0.texto', $texto)
        ->set('fotosBloques.'.$primero, UploadedFile::fake()->image('galeria.jpg', 900, 600))
        ->set('textos.adrian_bio', str_repeat('Biología de Formicidae. ', 300))->call('agregarBloque');
    $segundo = $editor->get('bloques')[1]['id'];
    $editor->set('bloques.1.titulo', 'Actividades de investigación')->set('bloques.1.texto', 'Trabajo de campo y nuevas publicaciones.')
        ->call('moverBloque', $segundo, -1)->call('guardar')->assertHasNoErrors();
    $publicado = app(PaginaLaboratorio::class)->leer();
    expect(array_column($publicado['bloques'], 'id'))->toBe([$segundo, $primero]);
    expect($publicado['textos']['adrian_bio'])->toBe(trim(str_repeat('Biología de Formicidae. ', 300)));
    $foto = $publicado['bloques'][1]['foto'];
    $url = route('laboratorio.foto', ['foto' => 'bloque-'.$primero, 'hash' => $foto['sha256']]);
    $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/webp');
    $this->get(route('laboratorio'))->assertOk()->assertSeeInOrder(['Actividades de investigación', 'Galería del laboratorio'])
        ->assertSee('Leer más')->assertSee('FINAL-DEL-TEXTO')->assertSee('<script>no ejecutar</script>')->assertDontSee('<script>no ejecutar</script>', false);
    // El formulario no puede sustituir la ruta de una foto ya publicada.
    $editor->set('bloques.1.foto', ['ruta' => 'otra/carpeta.webp', 'sha256' => str_repeat('a', 64)])
        ->set('bloques.1.texto', 'Descripción actualizada')->call('guardar')->assertHasNoErrors();
    expect(app(PaginaLaboratorio::class)->leer()['bloques'][1]['foto']['sha256'])->toBe($foto['sha256']);
    $editor->set('quitarFotosBloques.'.$primero, true)->call('guardar')->assertHasNoErrors();
    $this->get($url)->assertNotFound();
    $editor->call('quitarBloque', $primero)->call('guardar')->assertHasNoErrors();
    expect(array_column(app(PaginaLaboratorio::class)->leer()['bloques'], 'id'))->toBe([$segundo]);
});

test('el editor limita bloques y textos sin publicar bloques vacíos ni identidades manipuladas', function () {
    $admin = User::factory()->administrador()->create();
    $editor = Livewire::actingAs($admin)->test(ConfiguracionLaboratorio::class)->call('agregarBloque')
        ->call('guardar')->assertHasErrors('bloques.0.texto');
    $editor->set('bloques.0.texto', str_repeat('x', 20001))->call('guardar')->assertHasErrors('bloques.0.texto');
    $editor->set('bloques.0.texto', 'Contenido válido')->set('bloques.0.id', '../archivo')
        ->call('guardar')->assertHasErrors('bloques.0.id');
    expect(app(PaginaLaboratorio::class)->leer()['bloques'])->toBe([]);
    $editor = Livewire::actingAs($admin)->test(ConfiguracionLaboratorio::class);
    for ($i = 0; $i < 31; $i++) $editor->call('agregarBloque');
    $editor->assertHasErrors('bloques');
    expect($editor->get('bloques'))->toHaveCount(30);
});

test('leer más muestra inicialmente trescientas palabras y conserva el resto', function () {
    $corto = view('components.laboratorio-texto', ['texto' => implode(' ', range(1, 300)), 'attributes' => new \Illuminate\View\ComponentAttributeBag])->render();
    expect($corto)->not->toContain('<details');
    $largo = view('components.laboratorio-texto', ['texto' => implode(' ', range(1, 305)), 'attributes' => new \Illuminate\View\ComponentAttributeBag])->render();
    preg_match('/<p[^>]*>(.*?)<\/p>/s', $largo, $parrafo);
    expect(preg_match_all('/\S+/u', strip_tags($parrafo[1])))->toBe(300);
    expect($largo)->toContain('<details', '301 302 303 304 305');
});

test('la posición libre se publica se valida y permite volver a la composición original', function () {
    $admin = User::factory()->administrador()->create();
    $p = ['x' => 5, 'y' => 4, 'ancho' => 70, 'alto' => 12, 'fuente' => 2.2, 'capa' => 1];
    $editor = Livewire::actingAs($admin)->test(ConfiguracionLaboratorio::class)
        ->set('posiciones', ['texto-titulo' => $p])->call('guardar')->assertHasNoErrors();
    expect(app(PaginaLaboratorio::class)->leer()['posiciones']['texto-titulo']['x'])->toBe(5);
    $this->get(route('laboratorio'))->assertOk()->assertSee('data-lab-free', false)->assertSee('left:5%', false);
    $editor->set('posiciones.texto-titulo.x', 90)->call('guardar')->assertHasErrors('posiciones');
    expect(app(PaginaLaboratorio::class)->leer()['posiciones']['texto-titulo']['x'])->toBe(5);
    $editor->set('posiciones', [])->call('guardar')->assertHasNoErrors();
    $this->get(route('laboratorio'))->assertOk()->assertSee('lab-staff-grid', false)->assertDontSee('data-lab-free', false);
});
