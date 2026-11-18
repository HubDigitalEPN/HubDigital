<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\TaxonIndex;

uses(Tests\DatabaseFeatureTestCase::class);

test('el curador crea busca edita y desactiva una especie conservando su padre y la paginacion', function (): void {
    $padre = DB::table('taxonomia.taxones')->where('rango','genero')->first();
    expect($padre)->not->toBeNull();
    $nombre = $padre->nombre_cientifico.' qaprueba'.strtr(bin2hex(random_bytes(4)), '0123456789','abcdefghij');
    $ui = Livewire::actingAs(User::factory()->curador()->create())->test(TaxonIndex::class);
    $total = count($ui->get('taxones'));
    $ui->update(updates: ['nombreCientifico' => $nombre, 'rango' => 'especie', 'padreId' => $padre->id,
        'autor' => 'PRUEBA QA - SIN ESPECIE REAL', 'anioDescripcion' => 2026, 'showModal' => true])
        ->call('registrarTaxon')->assertHasNoErrors()->assertSet('showModal',false)->assertSet('busqueda',$nombre)
        ->assertViewHas('taxonesPaginados', fn ($filas) => count($filas) === 1 && $filas[0]['nombreCientifico'] === $nombre);
    expect(count($ui->get('taxones')))->toBe($total+1);
    $id = DB::table('taxonomia.taxones')->where('nombre_cientifico',$nombre)->value('id');
    $ui->call('abrirEditModal',$id)->update(updates:['editNombreCientifico'=>$nombre.'a','editAutor'=>'PRUEBA QA - EDITADA'])
        ->call('actualizarTaxon')->assertHasNoErrors()->assertSet('busqueda',$nombre.'a')
        ->assertViewHas('taxonesPaginados', fn ($filas) => count($filas) === 1 && $filas[0]['autor'] === 'PRUEBA QA - EDITADA');
    expect(DB::table('taxonomia.taxones')->where('id',$id)->value('padre_id'))->toBe($padre->id);
    $ui->call('desactivarTaxon',$id)->assertHasNoErrors();
    expect(DB::table('taxonomia.taxones')->where('id',$id)->value('estado'))->toBe('inactivo');
    $ui->set('busqueda','SIN_COINCIDENCIA_QA')->call('goToPage',999)->call('nextPage')->assertSet('page',1)
        ->assertViewHas('totalItems',0)->assertViewHas('taxonesPaginados',[]);
    $ui->set('busqueda','')->assertViewHas('totalItems',$total+1);
});
