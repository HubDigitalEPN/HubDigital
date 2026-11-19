<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\ActualizarCatalogoIndex;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\EspecimenIndex;

uses(Tests\DatabaseFeatureTestCase::class);

beforeEach(function (): void {
    $this->withoutExceptionHandling([Symfony\Component\HttpKernel\Exception\HttpException::class]);
    DB::statement("SET LOCAL statement_timeout = '10s'");
});

test('cada campo del alta tiene nombre accesible ayuda con ejemplo y enlace a su error', function (): void {
    $ui = Livewire::actingAs(User::factory()->curador()->create())->withQueryParams(['q'=>'QA-AYUDA-'])->test(EspecimenIndex::class)->call('abrirModal');
    $doc = new DOMDocument;
    @$doc->loadHTML('<?xml encoding="UTF-8">'.$ui->html());
    $xpath = new DOMXPath($doc);
    $campos = $xpath->query('//*[@data-catalogo-campo]');
    expect($campos->length)->toBe(27);
    expect($ui->snapshot['data'])->not->toHaveKeys(['taxones', 'entidades']);
    foreach ($campos as $campo) {
        $modelo = $campo->getAttribute('data-catalogo-campo');
        expect($xpath->query('.//button[@aria-haspopup="dialog"]', $campo)->length)->toBe(1);
        $control = $xpath->query('.//*[@id="captura-'.$modelo.'"]', $campo)->item(0);
        expect($control)->not->toBeNull();
        expect($xpath->query('.//*[@data-catalogo-control-fila]/*[@data-catalogo-control]', $campo)->length)->toBe(1);
        expect($xpath->query('.//*[@data-catalogo-control]//*[@data-catalogo-ayuda]', $campo)->length)->toBe(0);
        expect($xpath->query('.//*[@data-catalogo-control-fila]/*[@data-catalogo-ayuda="'.$modelo.'"]', $campo)->length)->toBe(1);
        $dialogo = $xpath->query('.//dialog', $campo)->item(0);
        expect($dialogo->textContent)->toContain('Ejemplo:', 'Cerrar ayuda');
    }
    $ui->call('registrarEspecimen')->assertHasErrors(['codigoCatalogo','taxonId','colector'])
        ->assertSee('La ficha no se guardó')->assertSee('El campo Colector es obligatorio.')
        ->assertSee('href="#captura-colector"', false);
    $ui->assertViewHas('taxones', fn ($taxones) => count($taxones) > 0);
    expect($ui->snapshot['data'])->not->toHaveKeys(['taxones', 'entidades']);
});

test('un taxón inactivo no aparece en el alta y tampoco se admite enviando su identificador', function (): void {
    $id = DB::table('taxonomia.taxones')->where('estado','activo')->value('id');
    expect($id)->not->toBeNull();
    DB::table('taxonomia.taxones')->where('id',$id)->update(['estado'=>'inactivo']);
    $codigo = 'QA-INACTIVO-'.Str::uuid();
    $ui = Livewire::actingAs(User::factory()->curador()->create())->withQueryParams(['q'=>$codigo])->test(EspecimenIndex::class)->call('abrirModal');
    $ui->assertViewHas('taxones', fn ($taxones) => !in_array($id, array_column($taxones, 'id'), true));
    $ui->update(updates:['codigoCatalogo'=>$codigo,'taxonId'=>$id,'fechaColecta'=>'2026-11-17','colector'=>'QA sintético'])
        ->call('registrarEspecimen')->assertHasErrors('taxonId')->assertSet('showModal',true);
    expect(DB::table('taxonomia.especimenes')->where('codigo_catalogo',$codigo)->exists())->toBeFalse();
});

test('la carga reconoce el separador de una cabecera inequívoca y conserva el cambio manual', function (string $separador, string $opcion): void {
    $codigo = 'QA-SEPARADOR-'.Str::uuid();
    $archivo = UploadedFile::fake()->createWithContent('matriz.csv', "\xEF\xBB\xBFoccurrenceID{$separador}individualCount\r\n{$codigo}{$separador}2\r\n");
    $antes = DB::table('taxonomia.especimenes')->count();
    $ui = Livewire::actingAs(User::factory()->curador()->create())->test(ActualizarCatalogoIndex::class)
        ->set('archivo',$archivo)->assertSet('delimitador',$opcion)->call('validar')->assertHasNoErrors();
    expect($ui->get('resultado')['nuevos'])->toBe(1);
    $ui->set('delimitador',$opcion === ',' ? ';' : ',')->assertSet('resultado',null)->assertSet('separadorDetectado',null);
    expect(DB::table('taxonomia.especimenes')->count())->toBe($antes);
})->with([[',',','],[';',';'],["\t",'tab']]);

test('la guía de la matriz explica los campos vigentes y el tamaño máximo del archivo', function (): void {
    Livewire::actingAs(User::factory()->curador()->create())->test(ActualizarCatalogoIndex::class)
        ->assertSee('Guía de la plantilla vigente')->assertSee('Ayuda: Matriz Excel o CSV')
        ->assertSee('Máximo 20 MB.')->assertSee('occurrenceID')->assertSee('decimalLatitude');
});
