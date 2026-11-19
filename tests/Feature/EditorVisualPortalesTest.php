<?php

use App\Livewire\Administracion\ConfiguracionTextosPortal;
use App\Models\User;
use App\Support\EditorPortal;
use App\Support\PortalCopy;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(Tests\DatabaseFeatureTestCase::class);
afterEach(fn()=>PortalCopy::forget());

test('la opcion unica integra laboratorio y fichas y las pantallas antiguas no redirigen', function (): void {
    $this->actingAs(User::factory()->administrador()->create());
    $this->get(route('admin.contenidos'))->assertOk()->assertSee('Vista editable:')->assertDontSee('El Laboratorio: estructura y fotos')->assertSee('Fichas del explorador');
    $this->get('/administracion/el-laboratorio')->assertNotFound();
    $this->get('/administracion/fichas-taxonomicas')->assertNotFound();
});

test('editar un texto visual actualiza PostgreSQL y el portal sin reescribir los otros campos', function (): void {
    $usuario=User::factory()->curador()->create();
    $otro=PortalCopy::text('inicio.hero_descripcion');
    Livewire::actingAs($usuario)->test(ConfiguracionTextosPortal::class)
        ->call('seleccionarTexto','inicio.hero_titulo')->assertSet('editorAbierto',true)
        ->set('textoActual','Título editorial QA')->call('guardarTexto')->assertHasNoErrors()->assertSet('editorAbierto',false);
    expect(DB::table('usuarios.textos_portal')->where('clave','inicio.hero_titulo')->value('contenido'))->toBe('Título editorial QA')
        ->and(PortalCopy::text('inicio.hero_descripcion'))->toBe($otro);
    $this->get('/')->assertOk()->assertSee('Título editorial QA');
});

test('una edicion concurrente no se sobrescribe con el formulario abierto', function (): void {
    $usuario=User::factory()->curador()->create();
    $panel=Livewire::actingAs($usuario)->test(ConfiguracionTextosPortal::class)->call('seleccionarTexto','inicio.hero_titulo');
    DB::table('usuarios.textos_portal')->updateOrInsert(['clave'=>'inicio.hero_titulo'],['contenido'=>'Edición concurrente QA','updated_at'=>now()]);
    $panel->set('textoActual','Valor desactualizado')->call('guardarTexto')->assertHasErrors('textoActual');
    expect(DB::table('usuarios.textos_portal')->where('clave','inicio.hero_titulo')->value('contenido'))->toBe('Edición concurrente QA');
});

test('los literales editoriales se guardan por portal y no se interpretan como HTML', function (): void {
    $usuario=User::factory()->curador()->create();
    $original='Leer más';
    Livewire::actingAs($usuario)->test(ConfiguracionTextosPortal::class)->call('seleccionarLiteral',$original)
        ->set('textoActual','Texto <script>QA</script>')->call('guardarTexto')->assertHasNoErrors();
    $clave='editorial.inicio.'.hash('sha256',$original);
    expect(json_decode(DB::table('usuarios.textos_portal')->where('clave',$clave)->value('contenido'),true)['texto'])->toBe('Texto <script>QA</script>');
    $html=$this->get('/')->getContent();
    expect($html)->toContain('textos-editoriales-datos')->toContain('\u003Cscript\u003E')->not->toContain('<script>QA</script>');
    expect($this->get('/depositos')->getContent())->not->toContain('Texto \u003Cscript');
});

test('el editor rechaza claves internas y datos que no proceden del inventario editorial', function (): void {
    $usuario=User::factory()->curador()->create();
    Livewire::actingAs($usuario)->test(ConfiguracionTextosPortal::class)->call('seleccionarTexto','database.password')->assertStatus(422);
    Livewire::actingAs($usuario)->test(ConfiguracionTextosPortal::class)->call('seleccionarLiteral','QA-DATO-CIENTIFICO-'.\Illuminate\Support\Str::uuid())->assertHasErrors('textoActual')->assertSet('editorAbierto',false);
});

test('todos los grupos de contenido editorial tienen campos y los portales tienen ruta real', function (): void {
    foreach(EditorPortal::paginas() as $pagina=>$ruta) expect(\Illuminate\Support\Facades\Route::has($ruta))->toBeTrue();
    foreach(config('portal-copy') as $grupo=>$campos) foreach($campos as $campo=>$texto) {
        expect($texto)->toBeString()->not->toBe('');
        expect(config('portal-copy.'.$grupo.'.'.$campo))->toBe($texto);
    }
});

test('el orden editorial se publica por pagina y rechaza selectores permisos ajenos y versiones desactualizadas', function (): void {
    $this->actingAs(User::factory()->curador()->create());
    $clave=\App\Support\DisposicionContenido::clave('portal:inicio');$version=EditorPortal::version($clave);
    $orden=['g-1234567890abcdef'=>['i-1111111111111111','i-2222222222222222']];
    Livewire::test(ConfiguracionTextosPortal::class)->call('guardarDisposicion',$orden,$version)->assertHasNoErrors();
    expect(\App\Support\DisposicionContenido::leer('portal:inicio'))->toBe($orden)
        ->and(\App\Support\DisposicionContenido::leer('portal:depositos'))->toBe([]);
    $this->get('/')->assertOk()->assertSee('disposicion-contenido-datos')->assertSee('g-1234567890abcdef');
    Livewire::test(ConfiguracionTextosPortal::class)->call('guardarDisposicion',[],$version)->assertHasErrors('textoActual');
    Livewire::test(ConfiguracionTextosPortal::class)->call('guardarDisposicion',['body'=>['<script>']],EditorPortal::version($clave))->assertHasErrors();
    Livewire::actingAs(User::factory()->depositante()->create())->test(ConfiguracionTextosPortal::class)->assertForbidden();
});

test('el editor contextual de laboratorio reconoce textos y bloques por su identidad', function (): void {
    $usuario=User::factory()->curador()->create();
    $panel=Livewire::actingAs($usuario)->test(\App\Livewire\Administracion\ConfiguracionLaboratorio::class)
        ->call('seleccionarElemento','texto-titulo')->assertSet('campoSeleccionado','titulo')->assertSet('textoAbierto',true)
        ->set('textos.titulo','Laboratorio editorial QA')->call('guardar')->assertHasNoErrors();
    expect(app(\App\Services\PaginaLaboratorio::class)->leer()['textos']['titulo'])->toBe('Laboratorio editorial QA');
    $panel->call('agregarBloque')->set('bloques.0.titulo','Bloque QA')->set('bloques.0.texto','Contenido editorial QA');
    $id=$panel->get('bloques')[0]['id'];
    $panel->call('seleccionarElemento','texto-bloque-'.$id.'-texto')->assertSet('campoSeleccionado','bloques.0.texto')
        ->set('bloques.0.texto','Contenido corregido en contexto')->call('guardar')->assertHasNoErrors();
    expect(app(\App\Services\PaginaLaboratorio::class)->leer()['bloques'][0]['texto'])->toBe('Contenido corregido en contexto');
    $panel->call('seleccionarElemento','texto-bloque-0000000000000000-texto')->assertStatus(422);
});
