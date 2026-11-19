<?php

use App\Livewire\Administracion\ConfiguracionTextosWizard;
use App\Livewire\Administracion\ConfiguracionTextosSistema;
use App\Models\User;
use App\Support\VistaPreviaWizard;
use App\Support\WizardCopy;
use App\Support\TextosSistema;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(Tests\DatabaseFeatureTestCase::class);
afterEach(function(){WizardCopy::forget();TextosSistema::olvidar();});

test('el editor recorre las plantillas reales de los siete pasos sin crear ni retomar expedientes', function(){
    $antes=DB::table('recepciones.solicitudes_deposito')->count();
    $ui=Livewire::actingAs(User::factory()->administrador()->create())->test(ConfiguracionTextosWizard::class);
    foreach(VistaPreviaWizard::pasos() as $paso=>$dato){
        $ui->set('pasoEditor',$paso)->assertSee('data-wizard-fragmento',false)->assertSee('Pasos para editar textos')->assertDontSee('Paso que quieres editar');
        $doc=new DOMDocument;@$doc->loadHTML('<?xml encoding="UTF-8">'.$ui->html());$x=new DOMXPath($doc);
        $region=$x->query('//*[@data-wizard-fragmento]')->item(0);expect($region)->not->toBeNull();
        foreach($x->query('.//*',$region) as $nodo)foreach($nodo->attributes as $a)expect($a->name)->not->toStartWith('wire:')->not->toStartWith('x-')->not->toStartWith('on');
        expect($x->query('.//dialog|.//script|.//ui-modal',$region)->length)->toBe(0);
        expect($x->query('.//*[@required or @aria-required or @pattern or @aria-invalid or @data-flux-loading-indicator]',$region)->length)->toBe(0);
        expect($x->query('.//*[contains(@class,"animate-spin")]',$region)->length)->toBe(0);
        if($paso===0)expect($x->query('.//input[@data-disposicion-model]',$region)->length)->toBe(5);
    }
    expect(DB::table('recepciones.solicitudes_deposito')->count())->toBe($antes);
});

test('las posiciones del wizard se guardan por paso sin modificar valores ni reglas del tramite',function(){
    $this->actingAs(User::factory()->administrador()->create());
    $clave=\App\Support\DisposicionContenido::clave('wizard:0');
    $version=\App\Support\EditorPortal::version($clave);
    $orden=['g-1234567890abcdef'=>['i-1111111111111111','i-2222222222222222']];
    Livewire::test(ConfiguracionTextosWizard::class)->call('guardarDisposicion',$orden,$version)->assertHasNoErrors();
    expect(\App\Support\DisposicionContenido::leer('wizard:0'))->toBe($orden)
        ->and(\App\Support\DisposicionContenido::leer('wizard:1'))->toBe([]);
    $this->get(route('admin.contenidos',['seccion'=>'depositos']))->assertOk()->assertSee('Guardar y publicar')->assertDontSee('Reorganizar posiciones')->assertDontSee('Otros estados y textos de este paso');
    Livewire::test(ConfiguracionTextosWizard::class)->call('guardarDisposicion',[], $version)->assertHasErrors('textoActual');
});

test('un texto seleccionado se publica en postgres y detecta una edicion concurrente', function(){
    $ui=Livewire::actingAs(User::factory()->administrador()->create())->test(ConfiguracionTextosWizard::class)->call('seleccionarTexto','tramite.titulo');
    $ui->set('textoActual','Modalidad QA del trámite')->call('guardarSeleccion')->assertHasNoErrors()->assertSet('editorAbierto',false);
    expect(WizardCopy::text('tramite.titulo'))->toBe('Modalidad QA del trámite');
    $ui->call('seleccionarTexto','tramite.titulo');
    DB::table('usuarios.textos_wizard')->where('clave','tramite.titulo')->update(['contenido'=>'Cambio de otra persona']);
    $ui->set('textoActual','No sobrescribir')->call('guardarSeleccion')->assertHasErrors('textoActual');
    expect(DB::table('usuarios.textos_wizard')->where('clave','tramite.titulo')->value('contenido'))->toBe('Cambio de otra persona');
});

test('los textos generales indican pantalla y campo y el editor conserva el permiso de administracion', function(){
    Livewire::actingAs(User::factory()->administrador()->create())->test(ConfiguracionTextosSistema::class)->assertSee('Ubicación y campo')->assertSee('Pantalla donde aparece');
    Livewire::actingAs(User::factory()->curador()->create())->test(ConfiguracionTextosWizard::class)->assertForbidden();
});

test('el formulario operativo consume la distribucion publicada y conserva sus campos obligatorios',function(){
    $this->actingAs(User::factory()->administrador()->create());
    $clave=\App\Support\DisposicionContenido::clave('wizard:0');
    \App\Support\DisposicionContenido::guardar('wizard:0',['g-1234567890abcdef'=>['i-1111111111111111','i-2222222222222222']],\App\Support\EditorPortal::version($clave));
    $this->actingAs(User::factory()->depositante()->create());
    $html=$this->get(route('depositos.solicitud.crear'))->assertOk()->getContent();
    expect($html)->toContain('data-disposicion-scope="wizard:0"')->toContain('g-1234567890abcdef');
    $doc=new DOMDocument;@$doc->loadHTML('<?xml encoding="UTF-8">'.$html);$x=new DOMXPath($doc);
    expect($x->query('//*[@data-disposicion-scope="wizard:0"]//input[@required]')->length)->toBeGreaterThanOrEqual(5);
});
