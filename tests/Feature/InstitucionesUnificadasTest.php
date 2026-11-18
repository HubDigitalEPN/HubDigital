<?php

declare(strict_types=1);

use App\Livewire\Administracion\InstitucionesCatalogo;
use App\Models\User;
use App\Services\DirectorioInstituciones;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\GeneradorActaPdfPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarEntidadDepositante\RegistrarEntidadDepositanteHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarEntidadDepositante\RegistrarEntidadDepositanteInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarEspecimen\RegistrarEspecimenHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarEspecimen\RegistrarEspecimenInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\EntidadDepositante;

uses(Tests\DatabaseFeatureTestCase::class);

function registroConDepositanteQa(string $entidad): string
{
    $codigo='QA-DIRECTORIO-'.Str::uuid();
    app(RegistrarEspecimenHandler::class)->handle(new RegistrarEspecimenInput(codigoCatalogo:$codigo,
        taxonId:DB::table('taxonomia.taxones')->value('id'),localidad:'PRUEBA QA',fechaColecta:'2026-11-10',
        colector:'Colector QA',entidadDepositanteId:$entidad));
    return $codigo;
}

test('una sola alta del curador queda disponible en solicitudes y en el formulario de especimenes', function (): void {
    $nombre='QA DIRECTORIO '.Str::uuid();
    Livewire::actingAs(User::factory()->curador()->create())->test(InstitucionesCatalogo::class)->call('nueva')
        ->update(updates:['nombre'=>$nombre,'tipo'=>'institucion','contacto'=>'Contacto QA'])
        ->call('guardar')->assertHasNoErrors()->assertSet('busqueda',$nombre);
    $portal=DB::table('usuarios.instituciones_catalogo')->where('nombre',$nombre)->sole();
    $entidad=DB::table('taxonomia.entidades_depositantes')->where('nombre',$nombre)->sole();
    $filas=app(DirectorioInstituciones::class)->consulta()->where('nombre',$nombre)->get();
    expect($filas)->toHaveCount(1)->and($filas[0]->institucion_id)->toBe($portal->id)
        ->and($filas[0]->entidad_id)->toBe($entidad->id)->and($entidad->contacto)->toBe('Contacto QA');
    $codigo=registroConDepositanteQa($entidad->id);
    expect(DB::table('taxonomia.especimenes')->where('codigo_catalogo',$codigo)->value('entidad_depositante_id'))->toBe($entidad->id);
});

test('editar una coincidencia univoca conserva nombres historicos identificadores relaciones y acta de entrega', function (): void {
    $nombre='QA HISTORICA '.Str::uuid();
    $entidad=app(RegistrarEntidadDepositanteHandler::class)->handle(new RegistrarEntidadDepositanteInput(
        nombre:mb_strtolower($nombre),tipo:'institucion',contacto:'Contacto anterior'));
    $uuid=(string)$entidad->id;
    $portal=DB::table('usuarios.instituciones_catalogo')->insertGetId(['nombre'=>$nombre,'activo'=>true,'created_at'=>now(),'updated_at'=>now()]);
    $codigo=registroConDepositanteQa($uuid);
    $fila=app(DirectorioInstituciones::class)->consulta()->where('nombre',$nombre)->sole();
    $ui=Livewire::actingAs(User::factory()->curador()->create())->test(InstitucionesCatalogo::class)
        ->call('editarDirectorio',$fila->id)->update(updates:['contacto'=>'Contacto corregido','usarEnColeccion'=>false])->call('guardar')->assertHasNoErrors();
    expect(DB::table('taxonomia.entidades_depositantes')->where('id',$uuid)->value('nombre'))->toBe(mb_strtolower($nombre))
        ->and(DB::table('taxonomia.entidades_depositantes')->where('id',$uuid)->value('contacto'))->toBe('Contacto corregido')
        ->and(DB::table('taxonomia.especimenes')->where('codigo_catalogo',$codigo)->value('entidad_depositante_id'))->toBe($uuid);
    $ui->call('cambiarEstado',$portal)->assertHasNoErrors();
    expect(DB::table('usuarios.instituciones_catalogo')->where('id',$portal)->value('activo'))->toBeFalse()
        ->and(app(DirectorioInstituciones::class)->consulta()->where('nombre',$nombre)->sole()->entidad_id)->toBe($uuid);
    app()->instance(GeneradorActaPdfPort::class,new class implements GeneradorActaPdfPort {
        public function generar(EntidadDepositante $entidad,array $especimenes): string {
            expect($especimenes)->toHaveCount(1); return 'actas/qa-directorio.pdf';
        }
    });
    $ui->call('generarActa',$uuid)->assertHasNoErrors()->assertSet('showActaModal',true)->assertSet('actaPdfRuta','actas/qa-directorio.pdf');
});

test('las personas de la coleccion conservan nombres largos y contactos sin crear una institucion del portal', function (): void {
    $nombre='QA '.str_repeat('Persona de referencia ',10);
    $out=app(RegistrarEntidadDepositanteHandler::class)->handle(new RegistrarEntidadDepositanteInput(nombre:$nombre,tipo:'persona',contacto:'Anterior'));
    $id=(string)$out->id; $total=DB::table('usuarios.instituciones_catalogo')->count();
    $fila=app(DirectorioInstituciones::class)->consulta()->where('entidad_id',$id)->sole();
    Livewire::actingAs(User::factory()->curador()->create())->test(InstitucionesCatalogo::class)
        ->call('editarDirectorio',$fila->id)->set('contacto','Contacto nuevo')->call('guardar')->assertHasNoErrors();
    expect(DB::table('taxonomia.entidades_depositantes')->where('id',$id)->value('nombre'))->toBe(trim($nombre))
        ->and(DB::table('usuarios.instituciones_catalogo')->count())->toBe($total);
});

test('una edicion concurrente se informa sin sobreescribir contacto ni disponibilidad', function (): void {
    $nombre='QA CONCURRENCIA '.Str::uuid();
    $s=app(DirectorioInstituciones::class); $s->guardar(null,'',['nombre'=>$nombre,'tipo'=>'institucion','contacto'=>'Anterior','solicitudes'=>true,'coleccion'=>true]);
    $fila=$s->consulta()->where('nombre',$nombre)->sole();
    $ui=Livewire::actingAs(User::factory()->curador()->create())->test(InstitucionesCatalogo::class)->call('editarDirectorio',$fila->id);
    DB::table('taxonomia.entidades_depositantes')->where('id',$fila->entidad_id)->update(['contacto'=>'Cambio concurrente']);
    $ui->update(updates:['nombre'=>$nombre.' cambiado','contacto'=>'No sobrescribir','usarEnSolicitudes'=>false])->call('guardar')->assertHasErrors('nombre');
    expect(DB::table('taxonomia.entidades_depositantes')->where('id',$fila->entidad_id)->value('contacto'))->toBe('Cambio concurrente')
        ->and(DB::table('usuarios.instituciones_catalogo')->where('id',$fila->institucion_id)->value('activo'))->toBeTrue();
});

test('los nombres duplicados y datos invalidos no generan copias en ninguno de los dos usos', function (): void {
    $nombre='QA DUPLICADO '.Str::uuid();
    app(DirectorioInstituciones::class)->guardar(null,'',['nombre'=>$nombre,'tipo'=>'institucion','contacto'=>'','solicitudes'=>true,'coleccion'=>true]);
    $ui=Livewire::actingAs(User::factory()->curador()->create())->test(InstitucionesCatalogo::class)->call('nueva')
        ->set('nombre',mb_strtolower($nombre))->call('guardar')->assertHasErrors('nombre');
    expect(DB::table('usuarios.instituciones_catalogo')->whereRaw('lower(nombre) = ?',[mb_strtolower($nombre)])->count())->toBe(1)
        ->and(DB::table('taxonomia.entidades_depositantes')->whereRaw('lower(nombre) = ?',[mb_strtolower($nombre)])->count())->toBe(1);
    $ui->update(updates:['nombre'=>'QA nueva '.Str::uuid(),'contacto'=>str_repeat('x',256)])->call('guardar')->assertHasErrors('contacto');
});

test('un fallo al crear el depositante revierte tambien el alta de la institucion del portal', function (): void {
    $nombre='QA FALLO '.Str::uuid();
    DB::listen(function(QueryExecuted $q)use($nombre):void {
        if(str_starts_with(strtolower($q->sql),'insert') && str_contains($q->sql,'entidades_depositantes') && in_array($nombre,$q->bindings,true)) throw new RuntimeException('Fallo QA posterior a la escritura');
    });
    expect(fn()=>app(DirectorioInstituciones::class)->guardar(null,'',['nombre'=>$nombre,'tipo'=>'institucion','contacto'=>'','solicitudes'=>true,'coleccion'=>true]))
        ->toThrow(RuntimeException::class,'Fallo QA');
    expect(DB::table('usuarios.instituciones_catalogo')->where('nombre',$nombre)->exists())->toBeFalse()
        ->and(DB::table('taxonomia.entidades_depositantes')->where('nombre',$nombre)->exists())->toBeFalse();
});

test('la pantalla anterior se retira y el menu conserva una sola entrada funcional', function (): void {
    $this->actingAs(User::factory()->curador()->create())->get('/inventario/taxonomia/entidades-depositantes')->assertNotFound();
    $html=$this->get(route('admin.instituciones'))->assertOk()->assertSee('Instituciones y depositantes')->getContent();
    $dom=new DOMDocument; @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html); $xpath=new DOMXPath($dom);
    // Flux genera una presentación expandida y otra emergente del mismo grupo.
    expect($xpath->query('//nav[@data-flux-sidebar-nav]//ui-disclosure//a[@href="'.route('admin.instituciones').'"]')->length)->toBe(1)
        ->and($xpath->query('//nav[@data-flux-sidebar-nav]//ui-menu//a[@href="'.route('admin.instituciones').'"]')->length)->toBe(1)
        ->and($xpath->query('//a[@href="'.url('/inventario/taxonomia/entidades-depositantes').'"]')->length)->toBe(0);
    expect(is_file(base_path('Modules/InventarioGestionColeccion/app/Presentation/Http/Controllers/SeguimientoFisico/GestionRegistrosTaxonomicos/EntidadDepositanteIndex.php')))->toBeFalse()
        ->and(is_file(base_path('Modules/InventarioGestionColeccion/resources/views/admin/taxonomia/entidades/index.blade.php')))->toBeFalse();
    $this->actingAs(User::factory()->depositante()->create())->get(route('admin.instituciones'))->assertForbidden();
    $this->get('/inventario/taxonomia/entidades-depositantes')->assertNotFound();
    Livewire::test(InstitucionesCatalogo::class)->assertForbidden();
});
