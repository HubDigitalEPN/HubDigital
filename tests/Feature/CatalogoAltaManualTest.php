<?php

declare(strict_types=1);

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\EspecimenIndex;

uses(Tests\DatabaseFeatureTestCase::class);

afterEach(fn () => Date::setTestNow());
beforeEach(fn () => DB::statement("SET LOCAL statement_timeout = '10s'"));

test('la tabla distingue la fecha original de la normalizada conservando ambas columnas', function (): void {
    $columnas = app(\Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\ColumnasCatalogo::class)->presentar(
        \Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services\RegistroColumnasEspecimen::todas());
    $porClave = array_column($columnas, null, 'clave');
    expect($porClave['fechaColecta']['etiqueta'])->toContain('normalizada')
        ->and($porClave['fechaVerbatim']['etiqueta'])->toContain('original')
        ->and($porClave['fechaColecta']['etiqueta'])->not->toBe($porClave['fechaVerbatim']['etiqueta']);
});

test('el alta propone la fecha de Ecuador durante el cambio de dia UTC', function (): void {
    Date::setTestNow(Carbon::parse('2026-11-18 03:30:00', 'UTC'));
    Livewire::actingAs(User::factory()->curador()->create())->withQueryParams(['q' => 'QA-ALTA-'])->test(EspecimenIndex::class)
        ->assertSet('fechaColecta', '2026-11-17')
        ->call('abrirModal')->assertSet('fechaColecta', '2026-11-17');
});

test('el alta rechaza datos opcionales invalidos antes de persistir', function (string $campo, string $valor): void {
    $codigo = 'QA-ALTA-'.Str::uuid();
    $taxon = DB::table('taxonomia.taxones')->value('id');
    expect($taxon)->not->toBeNull();
    Livewire::actingAs(User::factory()->curador()->create())->withQueryParams(['q' => $codigo])->test(EspecimenIndex::class)
        ->update(updates: ['codigoCatalogo' => $codigo, 'taxonId' => $taxon, 'fechaColecta' => '2026-11-10',
            'colector' => 'QA sintetico', 'showModal' => true, $campo => $valor])
        ->call('registrarEspecimen')->assertHasErrors([$campo])
        ->assertSet('showModal', true);
    expect(DB::table('taxonomia.especimenes')->where('codigo_catalogo', $codigo)->exists())->toBeFalse();
})->with([
    ['decimalLatitude', '91'], ['decimalLongitude', '-181'], ['individualCount', '-1'],
    ['individualCount', '2.5'], ['individualCount', 'texto'], ['elevationMinM', 'texto'],
    ['occurrenceId', str_repeat('x', 121)], ['entidadDepositanteId', 'no-es-uuid'],
]);

test('el curador registra una ficha completa y el codigo duplicado conserva el original', function (): void {
    $codigo = 'QA-ALTA-'.Str::uuid();
    $taxon = DB::table('taxonomia.taxones')->value('id');
    $ui = Livewire::actingAs(User::factory()->curador()->create())->withQueryParams(['q' => $codigo])->test(EspecimenIndex::class)
        ->update(updates: ['codigoCatalogo' => $codigo, 'taxonId' => $taxon, 'fechaColecta' => '2026-11-10',
            'colector' => 'QA sintetico', 'individualCount' => '5', 'decimalLatitude' => '-0.09',
            'decimalLongitude' => '-78.47', 'specimenNotes' => 'PRUEBA QA. NO DIVULGAR.', 'showModal' => true])
        ->call('registrarEspecimen')
        ->assertHasNoErrors()->assertSet('showModal', false);
    $ficha = DB::table('taxonomia.especimenes')->where('codigo_catalogo', $codigo)->first();
    expect($ficha->individual_count)->toBe(5)->and((float)$ficha->decimal_latitude)->toBe(-0.09);
    expect(DB::table('divulgacion.especimenes_divulgables')->where('especimen_id', $ficha->id)->exists())->toBeFalse();
    $ui->update(updates: ['codigoCatalogo' => $codigo, 'taxonId' => $taxon, 'fechaColecta' => '2026-11-10', 'colector' => 'Otro colector', 'showModal' => true])
        ->call('registrarEspecimen')->assertSet('showModal', true);
    expect(DB::table('taxonomia.especimenes')->where('codigo_catalogo', $codigo)->count())->toBe(1)
        ->and(DB::table('taxonomia.especimenes')->where('id', $ficha->id)->value('colector'))->toBe('QA sintetico');
});
