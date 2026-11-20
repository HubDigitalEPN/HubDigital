<?php

use App\Livewire\Administracion\CargosCatalogo;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(Tests\DatabaseFeatureTestCase::class);

test('el grid agrega paginas frescas y mantiene una ventana de tres paginas sin copias obsoletas',function(){
    $filas=[];for($n=0;$n<66;$n++)$filas[]=['nombre'=>'QA-CONT-'.str_pad((string)$n,3,'0',STR_PAD_LEFT),'activo'=>true,'created_at'=>now(),'updated_at'=>now()];
    DB::table('usuarios.cargos_catalogo')->insert($filas);
    $ui=Livewire::actingAs(User::factory()->administrador()->create())->test(CargosCatalogo::class)->set('busqueda','QA-CONT-');
    $ui->assertViewHas('cargos',fn($p)=>$p->count()===15 && $p->firstItem()===1 && $p->lastItem()===15);
    foreach([30,45,45] as $cantidad){$v=$ui->get('gridVentana');$ui->call('cargarGrillaContinua',1,$v['consulta'],$v['hasta'])->assertViewHas('cargos',fn($p)=>$p->count()===$cantidad);}
    $ui->assertViewHas('cargos',fn($p)=>$p->firstItem()===16 && $p->lastItem()===60);
    DB::table('usuarios.cargos_catalogo')->where('nombre','QA-CONT-020')->update(['nombre'=>'QA-CONT-020 MODIFICADO']);
    $ui->call('$refresh')->assertSee('QA-CONT-020 MODIFICADO');
    $v=$ui->get('gridVentana');$ui->call('cargarGrillaContinua',-1,$v['consulta'],$v['desde'])->assertViewHas('cargos',fn($p)=>$p->firstItem()===1 && $p->lastItem()===45);
    $v=$ui->get('gridVentana');$ui->call('cargarGrillaContinua',1,str_repeat('0',64),$v['hasta'])->assertSet('gridVentana',$v);
    $ui->set('busqueda','QA-CONT-065')->assertViewHas('cargos',fn($p)=>$p->count()===1 && $p->firstItem()===1)->assertSet('gridVentana.desde',1)->assertSet('gridVentana.hasta',1);
});

test('inventario y exportacion conservan identidad y seleccion al ampliar la consulta',function(){
    $filas=[];for($n=0;$n<62;$n++)$filas[]=['id'=>(string)Str::uuid(),'codigo_catalogo'=>'QA-GRID-'.str_pad((string)$n,3,'0',STR_PAD_LEFT),'estado'=>'disponible','localidad'=>'','colector'=>'PRUEBA QA'];
    foreach($filas as &$fila)$fila['catalog_number']=$fila['codigo_catalogo'];unset($fila);
    DB::table('taxonomia.especimenes')->insert($filas);
    $usuario=User::factory()->curador()->create();
    $ui=Livewire::actingAs($usuario)->withQueryParams(['q'=>'QA-GRID-'])->test(Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\EspecimenIndex::class)->set('perPage',25);
    $ui->assertViewHas('filas',fn($r)=>count($r)===25)
        ->assertSee('Filas por carga')->assertDontSee('Mostrando')->assertDontSeeHtml('wire:click="nextPage"');
    $v=$ui->get('gridVentana');$ui->call('cargarGrillaContinua',1,$v['consulta'],$v['hasta'])->assertViewHas('filas',fn($r)=>count($r)===50);
    $v=$ui->get('gridVentana');$ui->call('cargarGrillaContinua',1,$v['consulta'],$v['hasta'])->assertViewHas('filas',fn($r)=>count($r)===62 && count(array_unique(array_column($r,'id')))===62);
    $ui->call('cambiarFilasPorCarga', 50)->assertSet('perPage', 50)->assertSet('gridVentana.hasta', 1)
        ->assertViewHas('filas', fn ($r) => count($r) === 50);
    $ui->call('abrirBusqueda')->assertSet('consultaRapida', 'QA-GRID-')->assertSet('busquedaAbierta', true)
        ->set('consultaRapida', 'QA-GRID-061')->call('buscarRapido')->assertSet('busquedaAbierta', false)
        ->assertSet('gridVentana.hasta', 1)->assertViewHas('filas', fn ($r) => array_column($r, 'id') === [$filas[61]['id']]);
    $exportar=Livewire::actingAs($usuario)->test(Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\ExportarEspecimenesGbif::class)->set('fCatalogNumber','QA-GRID-')->call('buscar');
    expect($exportar->get('especimenes'))->toHaveCount(15);
    $exportar->set('seleccionados.'.$filas[0]['id'],true);
    $v=$exportar->get('gridVentana');$exportar->call('cargarGrillaContinua',1,$v['consulta'],$v['hasta']);
    expect($exportar->get('especimenes'))->toHaveCount(30)->and($exportar->get('seleccionados')[$filas[0]['id']])->toBeTrue();
});

test('taxones y localidades amplian su pagina sin duplicar identidades',function(){
    $usuario=User::factory()->curador()->create();
    $taxones=Livewire::actingAs($usuario)->test(Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\TaxonIndex::class);
    $v=$taxones->get('gridVentana');expect($v['paginas'])->toBeGreaterThan(1);
    $taxones->call('cargarGrillaContinua',1,$v['consulta'],$v['hasta'])->assertViewHas('taxonesPaginados',fn($r)=>count($r)===30 && count(array_unique(array_column($r,'id')))===30);
    $localidades=Livewire::actingAs($usuario)->test(Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\LocalidadIndex::class);
    $v=$localidades->get('gridVentana');expect($v['paginas'])->toBeGreaterThan(1);
    $localidades->call('cargarGrillaContinua',1,$v['consulta'],$v['hasta'])->assertViewHas('localidadesPaginadas',fn($r)=>count($r)===50 && count(array_unique(array_column($r,'id')))===50);
});
