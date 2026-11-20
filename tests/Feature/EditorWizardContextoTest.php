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

test('el editor del wizard muestra la disposicion publicada sin exponer controles de movimiento',function(){
    $this->actingAs(User::factory()->administrador()->create());
    $clave=\App\Support\DisposicionContenido::clave('wizard:0');
    $orden=['g-1234567890abcdef'=>['i-1111111111111111','i-2222222222222222']];
    \App\Support\DisposicionContenido::guardar('wizard:0',$orden,\App\Support\EditorPortal::version($clave));
    $this->get(route('admin.contenidos',['seccion'=>'depositos']))->assertOk()
        ->assertSee('Guardar y publicar texto')->assertSee('g-1234567890abcdef')
        ->assertDontSee('data-publicar-posiciones',false)->assertDontSee('datos-disposicion-herramientas',false)
        ->assertDontSee('Reorganizar posiciones')->assertDontSee('Otros estados y textos de este paso');
    expect(method_exists(ConfiguracionTextosWizard::class,'guardarDisposicion'))->toBeFalse()
        ->and(\App\Support\DisposicionContenido::leer('wizard:0'))->toBe($orden);
});

test('el nombre de cada paso y la etiqueta del permiso se publican con su propia clave', function(){
    $ui=Livewire::actingAs(User::factory()->administrador()->create())->test(ConfiguracionTextosWizard::class);
    $titular='Titular QA del permiso';
    $ui->assertSee('data-wizard-copy="solicitud.nombre_permiso"',false)
        ->assertSee('data-wizard-copy="solicitud.generar_pdf"',false)
        ->assertSee('data-wizard-copy="general.atras"',false)
        ->assertDontSee('Cargando el texto guardado')
        ->call('publicarTextoVisual','solicitud.nombre_permiso',WizardCopy::text('solicitud.nombre_permiso'),$titular)
        ->assertReturned(['guardado'=>true,'texto'=>$titular])->assertSee($titular);

    foreach(VistaPreviaWizard::pasos() as $id=>$paso){
        $clave='pasos.'.$paso[1];
        $ui->assertSee('data-wizard-paso="'.$id.'"',false)->assertSee('data-wizard-copy="'.$clave.'"',false)->assertSee($paso[0]);
    }
    $ui->call('publicarTextoVisual','pasos.envio',WizardCopy::text('pasos.envio'),'Envío QA')
        ->assertReturned(['guardado'=>true,'texto'=>'Envío QA'])->assertSee('Envío QA');

    $this->actingAs(User::factory()->depositante()->create());
    $html=$this->get(route('depositos.solicitud.crear'))->assertOk()->getContent();
    $doc=new DOMDocument;@$doc->loadHTML('<?xml encoding="UTF-8">'.$html);$x=new DOMXPath($doc);
    expect($x->query('//*[@data-wizard-copy="solicitud.nombre_permiso"]//*[@data-flux-label and normalize-space(.)="'.$titular.'"]')->length)->toBe(1)
        ->and($x->query('//*[@data-wizard-copy="solicitud.nombre_permiso"]//input[@required]')->length)->toBe(1)
        ->and($x->query('//*[@aria-label="Progreso de la solicitud"]//*[@title="Envío QA"]')->length)->toBe(1)
        ->and(WizardCopy::text('pasos.envio'))->toBe('Envío QA');
});

test('un texto seleccionado se publica en postgres y detecta una edicion concurrente', function(){
    $ui=Livewire::actingAs(User::factory()->administrador()->create())->test(ConfiguracionTextosWizard::class);
    $ui->call('publicarTextoVisual','tramite.titulo',WizardCopy::text('tramite.titulo'),'Modalidad QA del trámite')
        ->assertReturned(['guardado'=>true,'texto'=>'Modalidad QA del trámite']);
    expect(WizardCopy::text('tramite.titulo'))->toBe('Modalidad QA del trámite');
    DB::table('usuarios.textos_wizard')->where('clave','tramite.titulo')->update(['contenido'=>'Cambio de otra persona']);
    $ui->call('publicarTextoVisual','tramite.titulo','Modalidad QA del trámite','No sobrescribir')
        ->assertReturned(['error'=>'Otra persona cambió este texto. Recarga la página antes de volver a editar.']);
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

test('archivos y datos del editor muestran el contenido fijo del formulario y ocultan estados del expediente', function (): void {
    $usuario = User::factory()->administrador()->create(['first_name' => 'Titular de QA', 'last_name' => 'No mostrar']);
    $editor = Livewire::actingAs($usuario)->test(ConfiguracionTextosWizard::class);
    $fragmento = static function (string $html): array {
        $doc = new DOMDocument;
        @$doc->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new DOMXPath($doc);
        return [$xpath, $xpath->query('//*[@data-wizard-fragmento]')->item(0)];
    };

    [$xpath, $vista] = $fragmento($editor->set('pasoEditor', 3)->html());
    expect($vista->textContent)->toContain('Archivos requeridos', 'Copia del permiso de movilización', 'Solo PDF', 'Descargar ejemplo de referencia')
        ->not->toContain('Archivo no aceptado', 'Eliminar', 'Firma validada', 'cargados');
    expect($xpath->query('.//*[contains(@class, "border-dashed")]', $vista)->length)->toBe(2)
        ->and($xpath->query('.//input|.//button|.//template|.//*[@role="status" or @role="button"]', $vista)->length)->toBe(0);

    [$xpath, $vista] = $fragmento($editor->set('pasoEditor', 4)->html());
    expect($vista->textContent)->toContain('Datos de la solicitud', 'Solicitante', 'Nombre', 'Cargo', 'Institución', 'Material', 'N.º Individuos', 'Declara las cantidades')
        ->not->toContain($usuario->name, 'Investigador de ejemplo', 'VISTA-PREVIA', 'Localidad de ejemplo', 'Verificado', 'Falta ingresar', 'No indicada');
    expect($xpath->query('.//*[@data-disposicion-campo]', $vista)->length)->toBe(11)
        ->and($xpath->query('.//dd|.//button|.//input', $vista)->length)->toBe(0);

    $operativo = \Illuminate\Support\Facades\Blade::render(<<<'BLADE'
        <x-gestionprestamosrecepciones::dropzone nombre="Documento QA" propiedad="archivoQa" :cargado="true" archivo-nombre="archivo-qa.pdf" />
        <x-gestionprestamosrecepciones::sum-cell campo="N.º Individuos" :valor="24" :manual="true">Acción del expediente</x-gestionprestamosrecepciones::sum-cell>
        BLADE);
    expect($operativo)->toContain('wire:model="archivoQa"', 'eliminarDocumento', 'archivo-qa.pdf', 'Verificado', '24', 'Acción del expediente');
});
