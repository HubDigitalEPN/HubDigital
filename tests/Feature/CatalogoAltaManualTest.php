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
        ->assertSet('fechaColecta', '2026-11-17');
});

test('el alta rechaza datos opcionales invalidos antes de persistir', function (string $campo, string $valor): void {
    $codigo = 'QA-ALTA-'.Str::uuid();
    $taxon = DB::table('taxonomia.taxones')->value('id');
    expect($taxon)->not->toBeNull();
    Livewire::actingAs(User::factory()->curador()->create())->withQueryParams(['q' => $codigo])->test(EspecimenIndex::class)
        ->update(updates: ['taxonId' => $taxon, 'fechaColecta' => '2026-11-10',
            'colector' => 'QA sintetico', 'showModal' => true, 'occurrenceId' => $codigo, $campo => $valor])
        ->call('registrarEspecimen')->assertHasErrors([$campo])
        ->assertSet('showModal', true);
    expect(DB::table('taxonomia.especimenes')->where('occurrence_id', $codigo)->exists())->toBeFalse();
})->with([
    ['decimalLatitude', '91'], ['decimalLongitude', '-181'], ['individualCount', '-1'],
    ['individualCount', '2.5'], ['individualCount', 'texto'], ['individualCount', '2147483648'], ['elevationMinM', 'texto'],
    ['occurrenceId', str_repeat('x', 121)], ['entidadDepositanteId', 'no-es-uuid'],
]);

test('el curador guarda una ficha con código automático y localidades nuevas', function (): void {
    $marca = 'QA-ALTA-'.Str::uuid();
    $taxon = DB::table('taxonomia.taxones')->where('estado', 'activo')->value('id');
    $ui = Livewire::actingAs(User::factory()->curador()->create())->withQueryParams(['q' => $marca])->test(EspecimenIndex::class);
    $datos = ['taxonId' => $taxon, 'fechaColecta' => '2026-11-10', 'showModal' => true,
        'individualCount' => '5', 'decimalLatitude' => '-0.09', 'decimalLongitude' => '-78.47',
        'localidad' => $marca.' área', 'localidad2' => $marca.' parroquia', 'localidad3' => $marca.' sector'];

    $ui->update(updates: $datos + ['occurrenceId' => $marca, 'colector' => 'QA sintético'])
        ->call('registrarEspecimen')->assertHasNoErrors()->assertSet('showModal', false);
    $ficha = DB::table('taxonomia.especimenes')->where('id', $ui->get('ultimoCreadoId'))->first();
    expect($ficha->codigo_catalogo)->toMatch('/^MEPN-INV-[0-9A-Z]{26}$/')
        ->and($ficha->individual_count)->toBe(5)->and((float) $ficha->decimal_latitude)->toBe(-0.09)
        ->and([$ficha->localidad_area, $ficha->localidad2, $ficha->localidad3])
        ->toBe([$datos['localidad'], $datos['localidad2'], $datos['localidad3']]);
    $ui->assertSee('Ver última ficha creada')->assertDontSeeHtml('id="captura-codigoCatalogo"');
    expect(DB::table('divulgacion.especimenes_divulgables')->where('especimen_id', $ficha->id)->exists())->toBeFalse();
    $ui->set('showModal', true)->call('buscarOpcionesCaptura', 'localidad', $marca)
        ->assertReturned(fn (array $resultado) => array_column($resultado['opciones'], 'valor') === [$datos['localidad']]);

    $ui->update(updates: $datos + ['occurrenceId' => $marca.'-B', 'colector' => 'Otro colector'])
        ->call('registrarEspecimen')->assertHasNoErrors();
    $segunda = DB::table('taxonomia.especimenes')->where('id', $ui->get('ultimoCreadoId'))->first();
    expect($segunda->codigo_catalogo)->not->toBe($ficha->codigo_catalogo)
        ->and(DB::table('taxonomia.especimenes')->where('id', $ficha->id)->value('colector'))->toBe('QA sintético');
});
