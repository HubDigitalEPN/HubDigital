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

test('la actualización de perfiles conserva ediciones propias y los bloques guardados', function () {
    $contenido = ['textos' => ['adrian_bio' => 'Biólogo entomólogo.', 'david_bio' => 'Biografía editada por curaduría'], 'bloques' => [['id' => 'abcdef1234567890', 'texto' => 'Contenido propio']], 'posiciones' => ['texto-titulo' => ['x' => 5]]];
    DB::table('usuarios.textos_portal')->insert(['clave' => PaginaLaboratorio::CLAVE, 'contenido' => json_encode($contenido), 'created_at' => now(), 'updated_at' => now()]);
    $migracion = require database_path('migrations/2026_10_30_000001_completar_perfiles_laboratorio.php');
    $migracion->up(); $migracion->up();
    $actual = json_decode(DB::table('usuarios.textos_portal')->where('clave', PaginaLaboratorio::CLAVE)->value('contenido'), true);
    expect($actual['textos']['adrian_bio'])->toBe(config('pagina-laboratorio.textos.adrian_bio'))
        ->and($actual['textos']['david_bio'])->toBe('Biografía editada por curaduría')
        ->and($actual['bloques'])->toBe($contenido['bloques'])->and($actual['posiciones'])->toBe($contenido['posiciones']);
});

test('la página publica el texto solicitado y conserva la composición y navegación', function () {
    $this->get(route('laboratorio'))->assertOk()
        ->assertSee(config('pagina-laboratorio.textos.principal'))
        ->assertSeeInOrder(['>Inicio<', '>El Laboratorio<', 'Explorar la colección', 'Depósitos'], false)
        ->assertSee('data-lab-personal', false)->assertSee('Nuestro')->assertSee('Vladimir Carvajal')
        ->assertSee('mailto:adrian.troya@epn.edu.ec', false);
    expect(app(PaginaLaboratorio::class)->leer()['fotos'])->toHaveCount(12);
    $this->get(route('laboratorio'))->assertSeeInOrder(['Entomología ·', 'Mastozoología', 'Ornitología', 'Herpetología', 'Ictiología', 'Ecología'])
        ->assertSee('Dayana Togán')->assertSee('Michelle Vélez Cárdenas')->assertSee('Selene Báez')
        ->assertSee('images/laboratorio/equipo/adrian-retrato.jpg', false)
        ->assertSee('Paleontología')->assertSee('José Luis Román')
        ->assertDontSee('Perfiles institucionales')->assertDontSee('personal-referencia.png');
});

test('las ramas conservan su personal y los encuadres de retrato respetan los originales', function () {
    $pagina = app(PaginaLaboratorio::class)->leer();
    $ramas = config('pagina-laboratorio.ramas');
    expect(array_map(fn ($rama) => count($rama['personas']), $ramas))->toBe([
        'mastozoologia' => 1, 'ornitologia' => 2, 'herpetologia' => 3,
        'ictiologia' => 1, 'ecologia' => 1, 'paleontologia' => 1,
    ]);
    foreach ($pagina['fotos'] as $foto) {
        [$ancho, $alto] = getimagesize(public_path('images/laboratorio/equipo/'.$foto['archivo']));
        [$w, $h, $x, $y, $cw, $ch] = $foto['retrato'];
        expect([$w, $h])->toBe([$ancho, $alto]);
        expect($cw / $ch)->toBe(0.75)->and($x)->toBeGreaterThanOrEqual(0)->and($y)->toBeGreaterThanOrEqual(0)
            ->and($x + $cw)->toBeLessThanOrEqual($ancho)->and($y + $ch)->toBeLessThanOrEqual($alto);
    }
});

test('el personal ampliado permite posicionar también los treinta bloques completos', function () {
    $pagina = app(PaginaLaboratorio::class)->leer();
    $pagina['bloques'] = array_map(fn ($i) => ['id' => str_pad(dechex($i), 16, '0', STR_PAD_LEFT),
        'titulo' => 'Bloque '.$i, 'texto' => 'Texto del bloque '.$i, 'alt' => 'Foto '.$i,
        'foto' => ['ruta' => 'foto-local.webp', 'alt' => 'Foto '.$i]], range(1, 30));
    $posiciones = array_fill_keys(array_keys(\App\Services\DisenoLaboratorio::elementos($pagina)),
        ['x' => 5, 'y' => 4, 'ancho' => 70, 'alto' => 12, 'fuente' => 2, 'capa' => 1]);
    expect(count($posiciones))->toBeGreaterThan(120)
        ->and(\App\Services\DisenoLaboratorio::validar($posiciones, $pagina))->toHaveCount(count($posiciones));
});

test('el resumen del perfil tiene cuarenta palabras y el modal conserva foto y texto completo', function () {
    $pagina = app(PaginaLaboratorio::class)->leer();
    foreach ([40, 41] as $cantidad) {
        $pagina['textos']['vladimir_bio'] = implode(' ', range(1, $cantidad));
        $html = view('components.laboratorio-perfil-texto', ['persona' => 'vladimir', 'pagina' => $pagina, 'attributes' => new \Illuminate\View\ComponentAttributeBag])->render();
        preg_match('/<p[^>]*>(.*?)<\/p>/s', $html, $parrafo);
        expect(preg_match_all('/\S+/u', strip_tags($parrafo[1])))->toBe(40);
        if ($cantidad === 40) expect($html)->not->toContain('<dialog');
        else expect($html)->toContain('<dialog', 'aria-haspopup="dialog"', 'Cerrar perfil de Vladimir', '40 41', 'vladimir_carvajal.jpg');
    }
});

test('la ampliación del perfil actualiza solo las versiones predeterminadas y conserva ediciones propias', function () {
    $contenido = ['textos' => ['adrian_bio' => 'Biólogo entomólogo.', 'vladimir_bio' => 'Nota propia de curaduría'], 'fotos' => ['vladimir' => ['alt' => 'Foto propia']], 'posiciones' => ['texto-titulo' => ['x' => 5]]];
    DB::table('usuarios.textos_portal')->insert(['clave' => PaginaLaboratorio::CLAVE, 'contenido' => json_encode($contenido), 'created_at' => now(), 'updated_at' => now()]);
    $migracion = require database_path('migrations/2026_10_30_000002_ampliar_perfil_institucional_adrian.php');
    $migracion->up(); $migracion->up();
    $actual = json_decode(DB::table('usuarios.textos_portal')->where('clave', PaginaLaboratorio::CLAVE)->value('contenido'), true);
    expect($actual['textos']['adrian_bio'])->toBe(config('pagina-laboratorio.textos.adrian_bio'))
        ->and($actual['textos']['vladimir_bio'])->toBe('Nota propia de curaduría')
        ->and($actual['fotos'])->toBe($contenido['fotos'])->and($actual['posiciones'])->toBe($contenido['posiciones']);
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
